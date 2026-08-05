<?php

namespace App\Filament\Pages;

use App\Domain\IdentityAccess\Actions\ChangeOwnPassword;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class Profile extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Profile';

    protected string $view = 'filament.pages.profile';

    public ?string $current_password = null;

    public ?string $new_password = null;

    public ?string $new_password_confirmation = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('current_password')
                ->label('Current password')
                ->password()
                ->revealable()
                ->required(),
            TextInput::make('new_password')
                ->label('New password')
                ->password()
                ->revealable()
                ->required()
                ->confirmed(),
            TextInput::make('new_password_confirmation')
                ->label('Confirm new password')
                ->password()
                ->revealable()
                ->required(),
        ]);
    }

    public function changePassword(): void
    {
        $this->form->validate();

        $actor = $this->authenticatedActor();
        $context = $this->tenantContext();

        if ($context === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        app(ChangeOwnPassword::class)->execute(
            $actor,
            $context,
            (string) $this->current_password,
            (string) $this->new_password,
            app(CorrelationId::class)->value(),
        );

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->sendSuccessNotification('Password updated.');
    }

    private function authenticatedActor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $actor;
    }

    private function tenantContext(): ?TenantContext
    {
        $context = request()->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : null;
    }
}
