<?php

namespace App\Filament\Resources\Users;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class UserResource extends Resource
{
    use UsesTenantContextRoutes;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        app(UserPolicy::class)->viewAny(self::authenticatedActor())->authorize();
        $context = self::tenantContext();

        return TenantOwnedRecordQuery::forTenant($context, User::class)
            ->orderBy('id');
    }

    public static function canViewAny(): bool
    {
        return app(UserPolicy::class)->viewAny(self::authenticatedActor())->allowed();
    }

    public static function canCreate(): bool
    {
        return app(UserPolicy::class)->allowsCreate(self::authenticatedActor())->allowed();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof User
            && app(UserPolicy::class)->view(self::authenticatedActor(), $record)->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User
            && app(UserPolicy::class)->allowsUpdate(self::authenticatedActor(), $record)->allowed();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
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

    /**
     * @return list<Role>
     */
    public static function tenantRolesFromForm(mixed $submittedRoles): array
    {
        $validated = Validator::make(['roles' => $submittedRoles], [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct'],
        ])->validate();

        /** @var list<int> $roleIds */
        $roleIds = array_map(static fn (mixed $roleId): int => (int) $roleId, $validated['roles']);
        $roles = Role::query()
            ->where('tenant_id', self::tenantContext()->tenantId)
            ->where('guard_name', 'web')
            ->whereKey($roleIds)
            ->orderBy('id')
            ->get();

        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages([
                'roles' => 'Every selected role must belong to the current tenant.',
            ]);
        }

        return $roles->all();
    }
}
