<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropColumn(['tenant_id', 'is_active', 'lock_version']);
        });
    }
};
