<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedSmallInteger('audit_retention_months')->default(24);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE platform_settings ADD CONSTRAINT platform_settings_singleton_retention_check CHECK (`id` = 1 AND `audit_retention_months` BETWEEN 1 AND 120)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
