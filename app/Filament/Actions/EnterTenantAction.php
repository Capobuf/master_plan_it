<?php

namespace App\Filament\Actions;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Illuminate\Auth\Access\AuthorizationException;

final class EnterTenantAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'enterTenant';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Enter tenant')
            ->authorize('view')
            ->successRedirectUrl(fn (): string => Dashboard::getUrl())
            ->action(function (Tenant $record): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    throw new AuthorizationException('PERMISSION_DENIED');
                }

                app(EnterTenantContext::class)->execute(
                    $actor,
                    $record,
                    request()->session(),
                    app(CorrelationId::class)->value(),
                );
            });
    }
}
