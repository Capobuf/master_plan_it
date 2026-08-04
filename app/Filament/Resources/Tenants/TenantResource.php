<?php

namespace App\Filament\Resources\Tenants;

use App\Domain\Tenancy\Actions\DeactivateTenant;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Enums\TenantState;
use App\Filament\Actions\EnterTenantAction;
use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Tenants';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(255),
            TextInput::make('currency_code')->required()->maxLength(3),
            TextInput::make('language_code')->required()->maxLength(10),
            TextInput::make('timezone')->required()->maxLength(255),
            TextInput::make('default_vat_rate')->required()->maxLength(13),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('state')->badge(),
                TextColumn::make('currency_code'),
                TextColumn::make('timezone'),
            ])
            ->recordActions([
                EnterTenantAction::make(),
                Action::make('edit')
                    ->label('Edit')
                    ->authorize('update')
                    ->url(fn (Tenant $record): string => self::getUrl('edit', ['record' => $record])),
                Action::make('deactivate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate tenant')
                    ->modalDescription(fn (Tenant $record): string => "Enter the exact tenant code {$record->code} to deactivate this tenant.")
                    ->schema([
                        TextInput::make('confirmation_token')
                            ->label('Tenant code')
                            ->required(),
                    ])
                    ->authorize('deactivate')
                    ->visible(fn (Tenant $record): bool => $record->state === TenantState::Active)
                    ->action(function (Tenant $record, array $data): void {
                        app(DeactivateTenant::class)->execute(
                            static::authenticatedActor(),
                            $record,
                            $record->lock_version,
                            $data['confirmation_token'],
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('reactivate')
                    ->color('success')
                    ->authorize('reactivate')
                    ->visible(fn (Tenant $record): bool => $record->state === TenantState::Inactive)
                    ->action(function (Tenant $record): void {
                        app(ReactivateTenant::class)->execute(
                            static::authenticatedActor(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
            ]);
    }

    /**
     * @return Builder<Tenant>
     */
    public static function getEloquentQuery(): Builder
    {
        self::authorizeViewAny();

        return Tenant::query();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    public static function authenticatedActor(): User
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $actor;
    }
}
