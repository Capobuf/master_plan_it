<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('cost_center_id');
            $table->string('title');
            $table->enum('stage', ['idea', 'proposed', 'approved', 'deferred', 'rejected']);
            $table->unsignedBigInteger('deferred_target_planning_year_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deleted_by_at')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'stage', 'deleted_at']);
            $table->index(['tenant_id', 'deferred_target_planning_year_id', 'deleted_at'], 'projects_tenant_target_del_idx');
            $table->foreign(['tenant_id', 'cost_center_id'])
                ->references(['tenant_id', 'id'])->on('cost_centers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'deferred_target_planning_year_id'], 'projects_tenant_target_fk')
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'project_id'], 'expenses_tenant_project_fk')
                ->references(['tenant_id', 'id'])->on('projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign('expenses_tenant_project_fk');
        });

        Schema::dropIfExists('projects');
    }
};
