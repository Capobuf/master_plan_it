<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Actions\PromoteDeferredProjects;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DeferredProjectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        Carbon::setTestNow('2026-01-01 00:30:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_promotion_uses_tenant_year_and_is_idempotent_and_tenant_scoped(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
        $otherTenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
        $target = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $future = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $foreignTarget = PlanningYear::factory()->for($otherTenant)->create(['year_label' => 2026]);
        $due = Project::factory()->for($tenant)->create(['stage' => ProjectStage::Deferred, 'deferred_target_planning_year_id' => $target->getKey()]);
        $notDue = Project::factory()->for($tenant)->create(['stage' => ProjectStage::Deferred, 'deferred_target_planning_year_id' => $future->getKey()]);
        $foreign = Project::factory()->for($otherTenant)->create(['stage' => ProjectStage::Deferred, 'deferred_target_planning_year_id' => $foreignTarget->getKey()]);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);

        $action = app(PromoteDeferredProjects::class);
        $this->assertSame(1, $action->execute($actor, new TenantContext($tenant, $actor)));
        $this->assertSame(ProjectStage::Proposed, $due->refresh()->stage);
        $this->assertNull($due->deferred_target_planning_year_id);
        $this->assertSame(2, $due->lock_version);
        $this->assertSame(ProjectStage::Deferred, $notDue->refresh()->stage);
        $this->assertSame(ProjectStage::Deferred, $foreign->refresh()->stage);
        $this->assertSame(0, $action->execute($actor, new TenantContext($tenant, $actor)));
        $this->assertSame(1, $due->versions()->where('contents->stage', ProjectStage::Proposed->value)->count());
    }
}
