<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ABILITIES = [
        'tenant-settings.view',
        'tenant-settings.update',
    ];

    public function up(): void
    {
        $timestamp = now();

        foreach (self::ABILITIES as $ability) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $ability, 'guard_name' => 'web'],
                ['updated_at' => $timestamp, 'created_at' => $timestamp],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $administratorId = DB::table('roles')
            ->whereNull('tenant_id')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::ABILITIES)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: removing authorization names during rollback could lock out operators.
    }
};
