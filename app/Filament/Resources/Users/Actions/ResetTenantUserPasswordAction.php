<?php

namespace App\Filament\Resources\Users\Actions;

use App\Domain\IdentityAccess\Actions\ResetTenantUserPassword;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

final class ResetTenantUserPasswordAction
{
    public static function make(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset tenant user password')
            ->modalDescription('This invalidates all sessions and rotates the remember token for the selected user.')
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->authorize(fn (User $record): bool => app(UserPolicy::class)
                ->allowsUpdate(UserResource::authenticatedActor(), $record)
                ->allowed())
            ->action(function (User $record, array $data): void {
                app(ResetTenantUserPassword::class)->execute(
                    UserResource::authenticatedActor(),
                    UserResource::tenantContext(),
                    $record,
                    $data['password'],
                    app(CorrelationId::class)->value(),
                );
            });
    }
}
