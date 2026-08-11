<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_years', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'planning_years_tenant_id_id_unique');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'vendors_tenant_id_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_tenant_id_id_unique');
        });

        Schema::table('planning_years', function (Blueprint $table) {
            $table->dropUnique('planning_years_tenant_id_id_unique');
        });
    }
};
