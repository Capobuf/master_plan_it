<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_years', function (Blueprint $table): void {
            $table->enum('budget_state', ['preparation', 'approved', 'closed'])->default('preparation')->after('active');
            $table->timestamp('history_activated_at')->nullable()->after('budget_state');
            $table->index(['tenant_id', 'budget_state', 'year_label'], 'planning_years_budget_state_idx');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->after('cost_center_id');
            $table->index(['tenant_id', 'project_id', 'deleted_at'], 'contracts_tenant_project_del_idx');
            $table->foreign(['tenant_id', 'project_id'], 'contracts_tenant_project_fk')
                ->references(['tenant_id', 'id'])->on('projects')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `expense_rows` DROP CHECK `expense_rows_date_shape_spend`');
        DB::statement('ALTER TABLE `expense_rows` DROP CHECK `expense_rows_date_shape_period`');
        DB::statement('ALTER TABLE `expense_rows` DROP CHECK `expense_rows_date_shape_complete`');
        DB::statement('ALTER TABLE `expense_rows` DROP CHECK `expense_rows_confirmation_matrix`');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->decimal('approved_amount', 19, 2)->nullable()->after('contract_id');
            $table->enum('approved_basis', ['net', 'gross'])->nullable()->after('approved_amount');
            $table->unsignedBigInteger('current_planning_row_id')->nullable()->after('approved_basis');
            $table->unsignedBigInteger('moved_from_expense_id')->nullable()->after('current_planning_row_id');
            $table->unsignedBigInteger('credit_for_expense_id')->nullable()->after('moved_from_expense_id');
            $table->index(['tenant_id', 'current_planning_row_id'], 'expenses_tenant_plan_row_idx');
            $table->foreign(['tenant_id', 'current_planning_row_id'], 'expenses_tenant_plan_row_fk')
                ->references(['tenant_id', 'id'])->on('expense_rows')->restrictOnDelete();
            $table->foreign(['tenant_id', 'moved_from_expense_id'], 'expenses_tenant_moved_from_fk')
                ->references(['tenant_id', 'id'])->on('expenses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'credit_for_expense_id'], 'expenses_tenant_credit_for_fk')
                ->references(['tenant_id', 'id'])->on('expenses')->restrictOnDelete();
        });

        DB::statement("UPDATE expense_rows SET type = 'quote', confirmation_state = NULL, confirmed_by_user_id = NULL, confirmed_at = NULL WHERE type = 'actual' AND confirmation_state = 'to_confirm' AND is_system_managed = 1 AND manual_override_at IS NULL AND source_key IS NOT NULL");
        DB::statement("UPDATE expense_rows SET confirmation_state = NULL, confirmed_by_user_id = NULL, confirmed_at = NULL WHERE type = 'actual'");
        DB::statement("UPDATE expenses e JOIN (SELECT expense_id, MIN(id) AS row_id FROM expense_rows WHERE deleted_at IS NULL AND type IN ('estimate', 'quote') GROUP BY expense_id HAVING COUNT(*) = 1) p ON p.expense_id = e.id SET e.current_planning_row_id = p.row_id");
        DB::table('planning_years')->whereNull('history_activated_at')->update(['history_activated_at' => DB::raw('CURRENT_TIMESTAMP')]);

        Schema::create('approval_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->enum('kind', ['initial', 'variation']);
            $table->date('effective_date');
            $table->timestamp('recorded_at');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->enum('budget_basis', ['net', 'gross']);
            $table->foreignId('revision_batch_id')->constrained('revision_batches')->restrictOnDelete();
            $table->char('correlation_id', 36);
            $table->timestamps();

            $table->unique('correlation_id');
            $table->index(['tenant_id', 'planning_year_id', 'effective_date', 'id'], 'approval_operations_year_date_idx');
            $table->foreign(['tenant_id', 'planning_year_id'])
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
        });

        Schema::create('approval_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->unsignedBigInteger('expense_id');
            $table->decimal('previous_amount', 19, 2)->nullable();
            $table->decimal('new_amount', 19, 2);
            $table->decimal('delta_amount', 19, 2);
            $table->unsignedBigInteger('cost_center_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('expense_kind', 32);
            $table->enum('budget_basis', ['net', 'gross']);
            $table->timestamps();

            $table->unique(['approval_operation_id', 'expense_id']);
            $table->index(['tenant_id', 'planning_year_id', 'expense_id'], 'approval_items_year_expense_idx');
            $table->foreign(['tenant_id', 'planning_year_id'])
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
            $table->foreign(['tenant_id', 'expense_id'])
                ->references(['tenant_id', 'id'])->on('expenses')->restrictOnDelete();
        });

        Schema::table('revision_batch_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('planning_year_id')->nullable()->after('tenant_id');
            $table->enum('mutation', ['upsert', 'delete'])->default('upsert')->after('planning_year_id');
            $table->index(['tenant_id', 'planning_year_id', 'versionable_type', 'versionable_id'], 'revision_items_annual_subject_idx');
            $table->foreign(['tenant_id', 'planning_year_id'], 'revision_items_tenant_year_fk')
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('revision_batch_items', function (Blueprint $table): void {
            $table->dropForeign('revision_items_tenant_year_fk');
            $table->dropIndex('revision_items_annual_subject_idx');
            $table->dropColumn(['planning_year_id', 'mutation']);
        });
        Schema::dropIfExists('approval_items');
        Schema::dropIfExists('approval_operations');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign('expenses_tenant_plan_row_fk');
            $table->dropForeign('expenses_tenant_moved_from_fk');
            $table->dropForeign('expenses_tenant_credit_for_fk');
            $table->dropIndex('expenses_tenant_plan_row_idx');
            $table->dropColumn(['approved_amount', 'approved_basis', 'current_planning_row_id', 'moved_from_expense_id', 'credit_for_expense_id']);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign('contracts_tenant_project_fk');
            $table->dropIndex('contracts_tenant_project_del_idx');
            $table->dropColumn('project_id');
        });
        Schema::table('planning_years', function (Blueprint $table): void {
            $table->dropIndex('planning_years_budget_state_idx');
            $table->dropColumn(['budget_state', 'history_activated_at']);
        });
    }
};
