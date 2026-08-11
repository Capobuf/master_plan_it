<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $rolePivot = $columns['role_pivot_key'] ?? 'role_id';
        $permissionPivot = $columns['permission_pivot_key'] ?? 'permission_id';
        $tenantKey = $columns['team_foreign_key'];

        throw_if($tables === null || $tenantKey !== 'tenant_id', 'Permission team configuration is not loaded.');

        Schema::create($tables['permissions'], static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], static function (Blueprint $table) use ($tenantKey): void {
            $table->id();
            $table->unsignedBigInteger($tenantKey)->nullable();
            $table->index($tenantKey, 'roles_tenant_id_index');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique([$tenantKey, 'name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], static function (Blueprint $table) use ($tables, $columns, $permissionPivot, $tenantKey): void {
            $table->unsignedBigInteger($permissionPivot);
            $table->string('model_type');
            $table->unsignedBigInteger($columns['model_morph_key']);
            $table->index([$columns['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($permissionPivot)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->unsignedBigInteger($tenantKey);
            $table->index($tenantKey, 'model_has_permissions_tenant_id_index');
            $table->primary([$tenantKey, $permissionPivot, $columns['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tables['model_has_roles'], static function (Blueprint $table) use ($tables, $columns, $rolePivot, $tenantKey): void {
            $table->unsignedBigInteger($rolePivot);
            $table->string('model_type');
            $table->unsignedBigInteger($columns['model_morph_key']);
            $table->index([$columns['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($rolePivot)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->unsignedBigInteger($tenantKey);
            $table->index($tenantKey, 'model_has_roles_tenant_id_index');
            $table->primary([$tenantKey, $rolePivot, $columns['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tables['role_has_permissions'], static function (Blueprint $table) use ($tables, $rolePivot, $permissionPivot): void {
            $table->unsignedBigInteger($permissionPivot);
            $table->unsignedBigInteger($rolePivot);
            $table->foreign($permissionPivot)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->foreign($rolePivot)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary([$permissionPivot, $rolePivot], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }
};
