<?php

namespace App\Console\Commands;

use App\Domain\Projects\Actions\PromoteDeferredProjects;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Console\Command;
use RuntimeException;

final class PromoteDeferredProjectsCommand extends Command
{
    protected $signature = 'projects:promote-deferred';

    protected $description = 'Promote due Deferred projects to Proposed for every active Tenant.';

    public function handle(PromoteDeferredProjects $action, PlatformAdministrator $administrator): int
    {
        $actor = User::query()->whereNull('tenant_id')->where('is_active', true)->get()
            ->first(fn (User $user): bool => $administrator->hasProtectedRole($user));
        if (! $actor instanceof User) {
            throw new RuntimeException('An active protected Administrator is required for deferred project promotion.');
        }

        $promoted = 0;
        Tenant::query()->where('state', TenantState::Active->value)->orderBy('id')->each(function (Tenant $tenant) use ($action, $actor, &$promoted): void {
            $promoted += $action->execute($actor, new TenantContext($tenant, $actor));
        });
        $this->info("Promoted {$promoted} deferred project(s).");

        return self::SUCCESS;
    }
}
