<?php

namespace App\Filament\Resources\Vendors;

use App\Domain\MasterData\Actions\DeactivateVendor;
use App\Domain\MasterData\Actions\DeleteVendor;
use App\Domain\MasterData\Actions\ReactivateVendor;
use App\Domain\MasterData\Queries\VendorListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\Pages\VendorRevisionHistory;
use App\Models\ExpenseRow;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class VendorResource extends Resource
{
    use UsesTenantContextRoutes;

    protected static ?string $model = Vendor::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Vendors';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('vat_number')->label('VAT number')->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(255),
            Textarea::make('address')->maxLength(65535),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('vat_number')->label('VAT number'),
                TextColumn::make('email'),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->url(fn (Vendor $record): string => self::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Vendor $record): bool => self::canEdit($record)),
                Action::make('deactivate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Vendor $record): bool => $record->active)
                    ->authorize(fn (Vendor $record): bool => self::canDeactivate($record))
                    ->action(function (Vendor $record): void {
                        app(DeactivateVendor::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('reactivate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Vendor $record): bool => ! $record->active)
                    ->authorize(fn (Vendor $record): bool => self::canReactivate($record))
                    ->action(function (Vendor $record): void {
                        app(ReactivateVendor::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Vendor $record): bool => self::canDelete($record))
                    ->action(function (Vendor $record): void {
                        app(DeleteVendor::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('history')
                    ->url(fn (Vendor $record): string => self::getUrl('history', ['record' => $record]))
                    ->visible(fn (Vendor $record): bool => self::canViewRevisions($record)),
            ]);
    }

    /** @return Builder<Vendor> */
    public static function getEloquentQuery(): Builder
    {
        return app(VendorListQuery::class)->forTenant(self::authenticatedActor(), self::tenantContext());
    }

    public static function canViewAny(): bool
    {
        try {
            return app(VendorPolicy::class)->viewAny(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canCreate(): bool
    {
        try {
            return app(VendorPolicy::class)->create(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Vendor
            && app(VendorPolicy::class)->view(self::authenticatedActor(), $record)->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Vendor
            && app(VendorPolicy::class)->{'update'}(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDeactivate(Vendor $record): bool
    {
        return app(VendorPolicy::class)->deactivate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canReactivate(Vendor $record): bool
    {
        return app(VendorPolicy::class)->reactivate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof Vendor) {
            return false;
        }

        return app(VendorPolicy::class)->{'delete'}(self::authenticatedActor(), $record)->allowed()
            && ! ExpenseRow::withTrashed()
                ->where('tenant_id', self::tenantContext()->tenantId)
                ->where('vendor_id', $record->getKey())
                ->exists();
    }

    public static function canViewRevisions(Vendor $record): bool
    {
        return app(VendorPolicy::class)->viewRevisions(self::authenticatedActor(), $record)->allowed();
    }

    public static function canRestoreRevision(Vendor $record): bool
    {
        return app(VendorPolicy::class)->restoreRevision(self::authenticatedActor(), $record)->allowed();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'edit' => EditVendor::route('/{record}/edit'),
            'history' => VendorRevisionHistory::route('/{record}/history'),
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

    public static function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }
}
