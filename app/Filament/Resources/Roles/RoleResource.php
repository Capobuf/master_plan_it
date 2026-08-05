<?php

namespace App\Filament\Resources\Roles;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\User;
use App\Policies\RolePolicy;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

final class RoleResource extends Resource
{
    use UsesTenantContextRoutes;

    protected static ?string $model = Role::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Roles';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    /** @return Builder<Role> */
    public static function getEloquentQuery(): Builder
    {
        app(RolePolicy::class)->viewAny(self::authenticatedActor())->authorize();
        $context = self::tenantContext();

        return TenantOwnedRecordQuery::forTenant($context, Role::class)
            ->where('guard_name', 'web')
            ->orderBy('id');
    }

    public static function canViewAny(): bool
    {
        try {
            return app(RolePolicy::class)->viewAny(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canCreate(): bool
    {
        return app(RolePolicy::class)->allowsCreate(self::authenticatedActor())->allowed();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Role
            && app(RolePolicy::class)->view(self::authenticatedActor(), $record)->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Role
            && app(RolePolicy::class)->allowsUpdate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Role
            && app(RolePolicy::class)->allowsDelete(self::authenticatedActor(), $record)->allowed();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
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
