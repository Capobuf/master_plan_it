<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\RestoreProjectRevision;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Projects\Queries\ProjectRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ProjectRevisionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
    }

    public function test_restore_creates_new_revision_and_preserves_source_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $center = CostCenter::factory()->for($tenant)->create();
        $project = app(CreateProject::class)->execute($actor, $context, new SaveProjectData('Prima', (int) $center->getKey(), ProjectStage::Idea, null, null), (string) str()->uuid());
        $sourceBatch = app(ProjectRevisionQuery::class)->history($actor, $context, $project)->last();
        $source = app(ProjectRevisionQuery::class)->sourceVersion($actor, $context, $project, (int) $sourceBatch->getKey());
        $sourceContents = $source->contents;
        $project = app(UpdateProject::class)->execute($actor, $context, $project, new SaveProjectData('Seconda', (int) $center->getKey(), ProjectStage::Approved, null, 1), (string) str()->uuid());

        $restored = app(RestoreProjectRevision::class)->execute($actor, $context, $project, $source, 2, (string) str()->uuid());
        $this->assertSame('Prima', $restored->title);
        $this->assertSame(3, $restored->lock_version);
        $this->assertSame($sourceContents, $source->fresh()->contents);
        $this->assertDatabaseHas('revision_batches', ['root_subject_id' => $project->getKey(), 'operation' => 'restore', 'restored_from_version_id' => $source->getKey()]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('STALE_VERSION');
        app(RestoreProjectRevision::class)->execute($actor, $context, $restored, $source, 2, (string) str()->uuid());
    }

    public function test_terminally_deleted_project_cannot_be_restored_from_a_preserved_version(): void
    {
        $tenant = Tenant::factory()->create(['deletion_reason_required' => false]);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $center = CostCenter::factory()->for($tenant)->create();
        $project = app(CreateProject::class)->execute(
            $actor,
            $context,
            new SaveProjectData('Terminale', (int) $center->getKey(), ProjectStage::Idea, null, null),
            (string) str()->uuid(),
        );
        $source = $project->latestVersions()->firstOrFail();
        app(DeleteProject::class)->execute($actor, $context, $project, 1, null, (string) str()->uuid());

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('RESOURCE_NOT_FOUND');
        app(RestoreProjectRevision::class)->execute($actor, $context, $project, $source, 2, (string) str()->uuid());
    }
}
