<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ProjectLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
    }

    public function test_create_and_update_write_versions_with_optimistic_concurrency(): void
    {
        [$actor, $context] = $this->context();
        $center = CostCenter::factory()->for($context->tenant)->create();
        $project = app(CreateProject::class)->execute($actor, $context, new SaveProjectData(
            'Nuovo progetto', (int) $center->getKey(), ProjectStage::Idea, null, null,
        ), (string) str()->uuid());

        $this->assertSame(1, $project->lock_version);
        $this->assertDatabaseHas('revision_batches', ['tenant_id' => $context->tenantId, 'operation' => 'create']);

        $updated = app(UpdateProject::class)->execute($actor, $context, $project, new SaveProjectData(
            'Progetto aggiornato', (int) $center->getKey(), ProjectStage::Approved, null, 1,
        ), (string) str()->uuid());
        $this->assertSame(2, $updated->lock_version);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('STALE_VERSION');
        app(UpdateProject::class)->execute($actor, $context, $updated, new SaveProjectData(
            'Stale', (int) $center->getKey(), ProjectStage::Approved, null, 1,
        ), (string) str()->uuid());
    }

    /** @return array{User,TenantContext} */
    private function context(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);

        return [$actor, new TenantContext($tenant, $actor)];
    }
}
