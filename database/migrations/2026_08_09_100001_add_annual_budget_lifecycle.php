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
            $table->unsignedBigInteger('current_planning_row_id')->nullable()->after('contract_id');
            $table->unsignedBigInteger('moved_from_expense_id')->nullable()->after('current_planning_row_id');
            $table->unsignedBigInteger('credit_for_expense_id')->nullable()->after('moved_from_expense_id');
            $table->unique(['tenant_id', 'planning_year_id', 'id'], 'expenses_tenant_year_id_unique');
            $table->index(['tenant_id', 'current_planning_row_id'], 'expenses_tenant_plan_row_idx');
            $table->index(['tenant_id', 'planning_year_id', 'id', 'deleted_at'], 'expenses_budget_blocker_idx');
            $table->foreign(['tenant_id', 'current_planning_row_id'], 'expenses_tenant_plan_row_fk')
                ->references(['tenant_id', 'id'])->on('expense_rows')->restrictOnDelete();
            $table->foreign(['tenant_id', 'moved_from_expense_id'], 'expenses_tenant_moved_from_fk')
                ->references(['tenant_id', 'id'])->on('expenses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'credit_for_expense_id'], 'expenses_tenant_credit_for_fk')
                ->references(['tenant_id', 'id'])->on('expenses')->restrictOnDelete();
        });

        Schema::table('expense_rows', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'expense_id', 'id'], 'expense_rows_tenant_expense_id_unique');
            $table->index(['tenant_id', 'type', 'expense_id', 'deleted_at', 'id'], 'expense_rows_actual_blocker_idx');
            $table->index(['tenant_id', 'is_extra', 'expense_id', 'deleted_at', 'id'], 'expense_rows_extra_blocker_idx');
        });

        DB::statement("UPDATE expense_rows SET type = 'quote', confirmation_state = NULL, confirmed_by_user_id = NULL, confirmed_at = NULL WHERE type = 'actual' AND confirmation_state = 'to_confirm' AND is_system_managed = 1 AND manual_override_at IS NULL AND source_key IS NOT NULL");
        DB::statement("UPDATE expense_rows SET confirmation_state = NULL, confirmed_by_user_id = NULL, confirmed_at = NULL WHERE type = 'actual'");
        DB::statement("UPDATE expenses e JOIN (SELECT expense_id, MIN(id) AS row_id FROM expense_rows WHERE deleted_at IS NULL AND type IN ('estimate', 'quote') GROUP BY expense_id HAVING COUNT(*) = 1) p ON p.expense_id = e.id SET e.current_planning_row_id = p.row_id");
        DB::table('planning_years')->whereNull('history_activated_at')->update(['history_activated_at' => DB::raw('CURRENT_TIMESTAMP')]);

        Schema::create('budget_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->enum('status', ['active', 'annulled'])->default('active');
            $table->date('effective_date');
            $table->timestamp('recorded_at');
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approved_by_name');
            $table->text('approval_note')->nullable();
            $table->char('currency_code', 3);
            $table->enum('budget_basis', ['net', 'gross']);
            $table->decimal('total_net_amount', 19, 2);
            $table->decimal('total_vat_amount', 19, 2);
            $table->decimal('total_gross_amount', 19, 2);
            $table->decimal('total_official_amount', 19, 2);
            $table->unsignedInteger('contributor_count');
            $table->string('composition_schema_version', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('projection_version', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('composition_fingerprint', 71)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('approval_revision_batch_id');
            $table->char('correlation_id', 36)->charset('ascii');
            $table->timestamp('annulled_at')->nullable();
            $table->unsignedBigInteger('annulled_by_user_id')->nullable();
            $table->string('annulled_by_name')->nullable();
            $table->text('annulment_note')->nullable();
            $table->unsignedBigInteger('annulment_revision_batch_id')->nullable();
            $table->char('annulment_correlation_id', 36)->nullable()->charset('ascii');
            $table->unsignedBigInteger('active_planning_year_id')->nullable()
                ->storedAs("CASE WHEN `status` = 'active' THEN `planning_year_id` ELSE NULL END");
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'budget_approvals_tenant_id_unique');
            $table->unique(['tenant_id', 'planning_year_id', 'id'], 'budget_approvals_year_id_unique');
            $table->unique(['tenant_id', 'planning_year_id', 'id', 'budget_basis'], 'budget_approvals_item_parent_unique');
            $table->unique(['tenant_id', 'active_planning_year_id'], 'budget_approvals_active_year_unique');
            $table->index(['tenant_id', 'planning_year_id', 'recorded_at', 'id'], 'budget_approvals_history_idx');
            $table->index(['tenant_id', 'planning_year_id', 'effective_date', 'id'], 'budget_approvals_effective_idx');
            $table->index('correlation_id', 'budget_approvals_correlation_idx');
            $table->index('annulment_correlation_id', 'budget_approvals_annul_correlation_idx');
            $table->foreign(['tenant_id', 'planning_year_id'], 'budget_approvals_tenant_year_fk')
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approval_revision_batch_id'], 'budget_approvals_revision_fk')
                ->references(['tenant_id', 'id'])->on('revision_batches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'annulment_revision_batch_id'], 'budget_approvals_annul_revision_fk')
                ->references(['tenant_id', 'id'])->on('revision_batches')->restrictOnDelete();
            $table->foreign('annulled_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `budget_approvals` ADD CONSTRAINT `budget_approvals_count_check` CHECK (`contributor_count` > 0)');
        DB::statement('ALTER TABLE `budget_approvals` ADD CONSTRAINT `budget_approvals_measure_check` CHECK (`total_net_amount` + `total_vat_amount` = `total_gross_amount`)');
        DB::statement("ALTER TABLE `budget_approvals` ADD CONSTRAINT `budget_approvals_official_check` CHECK ((`budget_basis` = 'net' AND `total_official_amount` = `total_net_amount`) OR (`budget_basis` = 'gross' AND `total_official_amount` = `total_gross_amount`))");
        DB::statement("ALTER TABLE `budget_approvals` ADD CONSTRAINT `budget_approvals_fingerprint_check` CHECK (`composition_fingerprint` REGEXP '^sha256:[0-9a-f]{64}$')");
        DB::statement(<<<'SQL'
            ALTER TABLE `budget_approvals`
            ADD CONSTRAINT `budget_approvals_terminal_check` CHECK (
                (`status` = 'active' AND `annulled_at` IS NULL AND `annulled_by_user_id` IS NULL
                    AND `annulled_by_name` IS NULL AND `annulment_note` IS NULL
                    AND `annulment_revision_batch_id` IS NULL AND `annulment_correlation_id` IS NULL)
                OR
                (`status` = 'annulled' AND `annulled_at` IS NOT NULL AND `annulled_by_user_id` IS NOT NULL
                    AND `annulled_by_name` IS NOT NULL AND CHAR_LENGTH(TRIM(`annulment_note`)) > 0
                    AND `annulment_revision_batch_id` IS NOT NULL AND `annulment_correlation_id` IS NOT NULL)
            )
            SQL);

        Schema::create('budget_approval_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->unsignedBigInteger('budget_approval_id');
            $table->enum('budget_basis', ['net', 'gross']);
            $table->string('source_identity', 255)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('source_lock_version');
            $table->enum('component_kind', ['ordinary_current_planning', 'plafond_allocation']);
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('expense_row_id')->nullable();
            $table->enum('expense_kind', ['ordinary', 'plafond']);
            $table->string('expense_title');
            $table->enum('row_type', ['estimate', 'quote'])->nullable();
            $table->string('row_description')->nullable();
            $table->unsignedBigInteger('cost_center_id');
            $table->string('cost_center_name');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_name')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('project_title')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('contract_title')->nullable();
            $table->decimal('net_amount', 19, 2);
            $table->decimal('vat_amount', 19, 2);
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('official_amount', 19, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'budget_approval_items_tenant_id_unique');
            $table->unique(['tenant_id', 'budget_approval_id', 'source_identity'], 'budget_approval_items_source_unique');
            $table->index(['tenant_id', 'budget_approval_id', 'id'], 'budget_approval_items_approval_idx');
            $table->index(['tenant_id', 'budget_approval_id', 'expense_id', 'id'], 'budget_approval_items_expense_idx');
            $table->index(['tenant_id', 'budget_approval_id', 'cost_center_id', 'id'], 'budget_approval_items_cost_center_idx');
            $table->index(['tenant_id', 'budget_approval_id', 'vendor_id', 'id'], 'budget_approval_items_vendor_idx');
            $table->index(['tenant_id', 'budget_approval_id', 'project_id', 'id'], 'budget_approval_items_project_idx');
            $table->index(['tenant_id', 'budget_approval_id', 'contract_id', 'id'], 'budget_approval_items_contract_idx');
            $table->foreign(
                ['tenant_id', 'planning_year_id', 'budget_approval_id', 'budget_basis'],
                'budget_approval_items_parent_fk',
            )->references(['tenant_id', 'planning_year_id', 'id', 'budget_basis'])
                ->on('budget_approvals')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_source_version_check` CHECK (`source_lock_version` >= 1)');
        DB::statement('ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_measure_check` CHECK (`net_amount` + `vat_amount` = `gross_amount`)');
        DB::statement("ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_official_check` CHECK ((`budget_basis` = 'net' AND `official_amount` = `net_amount`) OR (`budget_basis` = 'gross' AND `official_amount` = `gross_amount`))");
        DB::statement(<<<'SQL'
            ALTER TABLE `budget_approval_items`
            ADD CONSTRAINT `budget_approval_items_kind_check` CHECK (
                (`component_kind` = 'ordinary_current_planning' AND `expense_kind` = 'ordinary'
                    AND `expense_row_id` IS NOT NULL AND `row_type` IS NOT NULL AND `row_description` IS NOT NULL
                    AND `source_identity` = CONCAT('expense-row:', `expense_row_id`))
                OR
                (`component_kind` = 'plafond_allocation' AND `expense_kind` = 'plafond'
                    AND `expense_row_id` IS NULL AND `row_type` IS NULL AND `row_description` IS NULL
                    AND `source_identity` = CONCAT('plafond-allocation:', `expense_id`))
            )
            SQL);
        DB::statement('ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_vendor_check` CHECK ((`vendor_id` IS NULL) = (`vendor_name` IS NULL))');
        DB::statement('ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_project_check` CHECK ((`project_id` IS NULL) = (`project_title` IS NULL))');
        DB::statement('ALTER TABLE `budget_approval_items` ADD CONSTRAINT `budget_approval_items_contract_check` CHECK ((`contract_id` IS NULL) = (`contract_title` IS NULL))');

        Schema::create('budget_rectifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->unsignedBigInteger('budget_approval_id');
            $table->enum('origin_phase', ['after_approval', 'after_closure']);
            $table->string('source_identity', 255)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('source_expense_id')->nullable();
            $table->unsignedBigInteger('source_expense_row_id')->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('revision_batch_id');
            $table->char('correlation_id', 36)->charset('ascii');
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'budget_rectifications_tenant_id_unique');
            $table->index(['tenant_id', 'planning_year_id', 'recorded_at', 'id'], 'budget_rectifications_year_idx');
            $table->index('correlation_id', 'budget_rectifications_correlation_idx');
            $table->foreign(['tenant_id', 'planning_year_id'], 'budget_rectifications_year_fk')
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
            $table->foreign(['tenant_id', 'planning_year_id', 'budget_approval_id'], 'budget_rectifications_approval_fk')
                ->references(['tenant_id', 'planning_year_id', 'id'])->on('budget_approvals')->restrictOnDelete();
            $table->foreign(['tenant_id', 'revision_batch_id'], 'budget_rectifications_revision_fk')
                ->references(['tenant_id', 'id'])->on('revision_batches')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `budget_rectifications` ADD CONSTRAINT `budget_rectifications_note_check` CHECK (CHAR_LENGTH(TRIM(`note`)) > 0)');

        Schema::create('budget_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('planning_year_id');
            $table->unsignedBigInteger('budget_approval_id');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('revision_batch_id');
            $table->char('correlation_id', 36)->charset('ascii');
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'budget_closures_tenant_id_unique');
            $table->index(['tenant_id', 'planning_year_id', 'recorded_at', 'id'], 'budget_closures_year_idx');
            $table->index('correlation_id', 'budget_closures_correlation_idx');
            $table->foreign(['tenant_id', 'planning_year_id'], 'budget_closures_year_fk')
                ->references(['tenant_id', 'id'])->on('planning_years')->restrictOnDelete();
            $table->foreign(['tenant_id', 'planning_year_id', 'budget_approval_id'], 'budget_closures_approval_fk')
                ->references(['tenant_id', 'planning_year_id', 'id'])->on('budget_approvals')->restrictOnDelete();
            $table->foreign(['tenant_id', 'revision_batch_id'], 'budget_closures_revision_fk')
                ->references(['tenant_id', 'id'])->on('revision_batches')->restrictOnDelete();
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
        Schema::dropIfExists('budget_closures');
        Schema::dropIfExists('budget_rectifications');
        Schema::dropIfExists('budget_approval_items');
        Schema::dropIfExists('budget_approvals');

        Schema::table('expense_rows', function (Blueprint $table): void {
            $table->dropIndex('expense_rows_actual_blocker_idx');
            $table->dropIndex('expense_rows_extra_blocker_idx');
            $table->dropUnique('expense_rows_tenant_expense_id_unique');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign('expenses_tenant_plan_row_fk');
            $table->dropForeign('expenses_tenant_moved_from_fk');
            $table->dropForeign('expenses_tenant_credit_for_fk');
            $table->dropIndex('expenses_tenant_plan_row_idx');
            $table->dropIndex('expenses_budget_blocker_idx');
            $table->dropUnique('expenses_tenant_year_id_unique');
            $table->dropColumn(['current_planning_row_id', 'moved_from_expense_id', 'credit_for_expense_id']);
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
