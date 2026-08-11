<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY = [
        'platform.users.manage',
        'platform.roles.manage',
        'deletion-reason-setting.manage',
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::LEGACY)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Forward-only: retired authorization names must not be silently restored.
    }
};
