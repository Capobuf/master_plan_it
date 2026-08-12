<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->enum('kind', ['ordinary', 'plafond']);
            $table->string('title');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'planning_year_id', 'deleted_at'], 'expenses_tenant_year_del_idx');
            $table->index(['tenant_id', 'cost_center_id', 'deleted_at'], 'expenses_tenant_cc_del_idx');
            $table->index(['tenant_id', 'project_id', 'deleted_at'], 'expenses_tenant_project_del_idx');
            $table->index(['tenant_id', 'contract_id', 'deleted_at'], 'expenses_tenant_contract_del_idx');

            $table->foreign(['tenant_id', 'planning_year_id'])
                ->references(['tenant_id', 'id'])
                ->on('planning_years')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'cost_center_id'])
                ->references(['tenant_id', 'id'])
                ->on('cost_centers')
                ->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
