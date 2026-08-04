<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year_label');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'year_label']);
            $table->index(['tenant_id', 'active', 'year_label']);
            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_years');
    }
};
