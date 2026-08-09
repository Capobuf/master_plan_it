<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DeleteProjectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
    }

    public function test_linked_current_expense_blocks_delete_without_detach_or_cascade_then_deleted_expense_allows_terminal_delete(): void
    {
        [$actor, $context] = $this->context();
        $project = Project::factory()->for($context->tenant)->create();
        $expense = Expense::factory()->for($context->tenant)->create(['project_id' => $project->getKey()]);
        try {
            app(DeleteProject::class)->execute($actor, $context, $project, 1, null, (string) str()->uuid());
            $this->fail('Linked project deletion was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('PROJECT_HAS_LINKED_EXPENSES', $exception->getMessage());
        }
        $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'deleted_at' => null]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'project_id' => $project->getKey(), 'deleted_at' => null]);

        $expense->delete();
        app(DeleteProject::class)->execute($actor, $context, $project->refresh(), 1, '  Chiuso  ', (string) str()->uuid());
        $this->assertSoftDeleted('projects', ['id' => $project->getKey(), 'deletion_reason' => 'Chiuso']);
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'project_id' => $project->getKey()]);
    }

    public function test_tenant_setting_requires_trimmed_reason_and_maximum_is_enforced(): void
    {
        [$actor, $context] = $this->context(['deletion_reason_required' => true]);
        $project = Project::factory()->for($context->tenant)->create();
        try {
            app(DeleteProject::class)->execute($actor, $context, $project, 1, '   ', (string) str()->uuid());
            $this->fail('Missing required reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('deletion_reason', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(DeleteProject::class)->execute($actor, $context, $project->refresh(), 1, str_repeat('a', 501), (string) str()->uuid());
    }

    /** @param array<string,mixed> $tenantAttributes @return array{User,TenantContext} */
    private function context(array $tenantAttributes = []): array
    {
        $tenant = Tenant::factory()->create($tenantAttributes);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);

        return [$actor, new TenantContext($tenant, $actor)];
    }
}
