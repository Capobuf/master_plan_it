<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ABILITIES = [
        'tenant-settings.view', 'tenant-settings.update',
        'tenant-users.view', 'tenant-users.manage',
        'tenant-roles.view', 'tenant-roles.manage',
    ];

    public function up(): void
    {
        foreach (self::ABILITIES as $ability) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $ability, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }

        $administratorId = DB::table('roles')
            ->whereNull('tenant_id')->where('name', 'Administrator')->where('guard_name', 'web')
            ->value('id');

        if ($administratorId !== null) {
            $permissionIds = DB::table('permissions')->whereIn('name', self::ABILITIES)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: removing authorization names during rollback could lock out operators.
    }
};
