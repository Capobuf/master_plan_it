<?php

namespace Tests\Feature\Revisions;

use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version as ApplicationVersion;
use Composer\InstalledVersions;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Overtrue\LaravelVersionable\Version;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
use Tests\TestCase;

class VersioningPackageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const VENDOR_VERSIONED_FIELDS = [
        'name',
        'vat_number',
        'email',
        'phone',
        'address',
        'active',
    ];

    /** @var list<string> */
    private const COST_CENTER_VERSIONED_FIELDS = [
        'parent_id',
        'name',
        'active',
    ];

    public function test_locked_package_exposes_the_measured_snapshot_and_attribution_api(): void
    {
        $this->assertSame('v6.0.0', InstalledVersions::getPrettyVersion('overtrue/laravel-versionable'));
        $this->assertSame('SNAPSHOT', VersionStrategy::SNAPSHOT->value);
        $this->assertTrue(method_exists(Versionable::class, 'getVersionableAttributes'));
        $this->assertTrue(method_exists(Versionable::class, 'getVersionUserId'));
        $this->assertTrue(method_exists(Version::class, 'revertWithoutSaving'));
        $this->assertTrue(method_exists(Version::class, 'revert'));
    }

    public function test_vendor_and_cost_center_use_snapshots_with_exact_business_field_allowlists(): void
    {
        $this->assertVersionableModelContract(new Vendor, self::VENDOR_VERSIONED_FIELDS);
        $this->assertVersionableModelContract(new CostCenter, self::COST_CENTER_VERSIONED_FIELDS);
    }

    public function test_snapshot_records_the_authenticated_actor_while_correlation_remains_application_owned(): void
    {
        $this->assertVersioningInfrastructureAvailable();

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->actingAs($actor);

        $vendor = Vendor::factory()->for($tenant)->create([
            'name' => 'Initial supplier',
            'vat_number' => 'IT01234567890',
            'email' => 'supplier@example.test',
            'phone' => '+390100000000',
            'address' => 'Via Roma 1',
            'active' => true,
        ]);
        $vendor->forceFill([
            'name' => 'Updated supplier',
            'active' => false,
            'lock_version' => 2,
        ])->save();

        $latest = $vendor->latestVersion()->firstOrFail();
        $persistedFields = array_keys($latest->contents);
        $expectedFields = self::VENDOR_VERSIONED_FIELDS;
        sort($persistedFields);
        sort($expectedFields);

        $this->assertSame($actor->getKey(), $latest->user_id);
        $this->assertSame($expectedFields, $persistedFields);
        $this->assertSame('Updated supplier', $latest->contents['name']);
        $this->assertSame('IT01234567890', $latest->contents['vat_number']);
        $this->assertSame('supplier@example.test', $latest->contents['email']);
        $this->assertSame('+390100000000', $latest->contents['phone']);
        $this->assertSame('Via Roma 1', $latest->contents['address']);
        $this->assertSame(0, $latest->contents['active']);
        $this->assertFalse(Schema::hasColumn('versions', 'correlation_id'));
    }

    public function test_soft_deleted_master_data_keeps_readable_snapshot_history(): void
    {
        $this->assertVersioningInfrastructureAvailable();

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->actingAs($actor);

        $costCenter = CostCenter::factory()->for($tenant)->create([
            'name' => 'Operations',
        ]);
        $costCenter->forceFill([
            'name' => 'Operations Italy',
            'lock_version' => 2,
        ])->save();

        $versionIds = $costCenter->versions()->orderOldestFirst()->pluck('id')->all();
        $costCenter->delete();

        $this->assertNull(CostCenter::query()->find($costCenter->getKey()));
        $this->assertNotNull(CostCenter::withTrashed()->find($costCenter->getKey()));
        $this->assertSame(
            $versionIds,
            Version::query()
                ->where('versionable_type', $costCenter->getMorphClass())
                ->where('versionable_id', $costCenter->getKey())
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            ['Operations', 'Operations Italy'],
            Version::query()
                ->where('versionable_type', $costCenter->getMorphClass())
                ->where('versionable_id', $costCenter->getKey())
                ->orderBy('id')
                ->get()
                ->map(fn (Version $version): string => $version->contents['name'])
                ->all(),
        );
    }

    public function test_direct_revert_cannot_mutate_an_application_model(): void
    {
        [$originalVersion, $vendor] = $this->createVersionedVendor();

        $this->assertDirectRestoreIsRejected($originalVersion, 'revert');

        $this->assertSame('Current supplier', $vendor->refresh()->name);
        $this->assertSame(2, $vendor->lock_version);
    }

    public function test_direct_revert_without_saving_cannot_mutate_an_application_model(): void
    {
        [$originalVersion, $vendor] = $this->createVersionedVendor();

        $this->assertDirectRestoreIsRejected($originalVersion, 'revertWithoutSaving');

        $this->assertSame('Current supplier', $vendor->refresh()->name);
        $this->assertSame(2, $vendor->lock_version);
    }

    /**
     * @return array{ApplicationVersion, Vendor}
     */
    private function createVersionedVendor(): array
    {
        $this->assertVersioningInfrastructureAvailable();

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->actingAs($actor);

        $vendor = Vendor::factory()->for($tenant)->create([
            'name' => 'Original supplier',
        ]);
        $vendor->forceFill([
            'name' => 'Current supplier',
            'lock_version' => 2,
        ])->save();
        $originalVersion = $vendor->oldestVersions()->firstOrFail();

        $this->assertSame(ApplicationVersion::class, $originalVersion::class);
        if (! $originalVersion instanceof ApplicationVersion) {
            $this->fail('Versionable models must resolve the application Version model.');
        }

        return [$originalVersion, $vendor];
    }

    private function assertDirectRestoreIsRejected(ApplicationVersion $version, string $operation): void
    {
        $inMemoryBusinessModel = $version->versionable;
        $inMemoryAttributesBefore = $inMemoryBusinessModel->getAttributes();
        $persistedBusinessModelBefore = $inMemoryBusinessModel->fresh();
        $this->assertInstanceOf(Vendor::class, $persistedBusinessModelBefore);
        $persistedAttributesBefore = $persistedBusinessModelBefore->getAttributes();

        try {
            match ($operation) {
                'revert' => $version->revert(),
                'revertWithoutSaving' => $version->revertWithoutSaving(),
            };

            $this->fail("Direct package {$operation}() must be rejected.");
        } catch (DomainException $exception) {
            $this->assertSame(DomainException::class, $exception::class);
            $this->assertSame('REVISION_RESTORE_INVALID', $exception->getMessage());
        }

        $this->assertSame($inMemoryAttributesBefore, $inMemoryBusinessModel->getAttributes());
        $persistedBusinessModelAfter = $inMemoryBusinessModel->fresh();
        $this->assertInstanceOf(Vendor::class, $persistedBusinessModelAfter);
        $this->assertSame($persistedAttributesBefore, $persistedBusinessModelAfter->getAttributes());
    }

    /**
     * @param  list<string>  $expectedFields
     */
    private function assertVersionableModelContract(Model $model, array $expectedFields): void
    {
        $this->assertContains(Versionable::class, class_uses_recursive($model));
        $this->assertSame(VersionStrategy::SNAPSHOT, $model->getVersionStrategy());
        $this->assertSame($expectedFields, $model->getVersionable());
        $this->assertSame([], array_intersect(
            ['tenant_id', 'lock_version', 'created_at', 'updated_at', 'deleted_at'],
            $model->getVersionable(),
        ));
    }

    private function assertVersioningInfrastructureAvailable(): void
    {
        $this->assertTrue(
            Schema::hasTable('versions'),
            'The published Overtrue versions migration is missing.',
        );
        $this->assertContains(Versionable::class, class_uses_recursive(Vendor::class));
        $this->assertContains(Versionable::class, class_uses_recursive(CostCenter::class));
    }
}
