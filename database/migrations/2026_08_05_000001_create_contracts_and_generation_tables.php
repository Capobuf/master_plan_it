<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->date('renewal_date')->nullable();
            $table->unsignedInteger('renewal_notice_days')->nullable();
            $table->text('renewal_notes')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deleted_by_at')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'active', 'deleted_at']);
            $table->foreign(['tenant_id', 'vendor_id'])->references(['tenant_id', 'id'])->on('vendors')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cost_center_id'])->references(['tenant_id', 'id'])->on('cost_centers')->restrictOnDelete();
        });

        Schema::create('contract_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('contract_id');
            $table->uuid('source_rule_key');
            $table->date('effective_start');
            $table->date('effective_end');
            $table->enum('billing_cycle', ['monthly', 'annual']);
            $table->decimal('quantity', 19, 2)->nullable();
            $table->decimal('unit_price', 19, 2)->nullable();
            $table->decimal('entered_amount', 19, 2);
            $table->boolean('amount_includes_vat');
            $table->decimal('vat_rate', 12, 2);
            $table->decimal('net_amount', 19, 2);
            $table->decimal('vat_amount', 19, 2);
            $table->decimal('gross_amount', 19, 2);
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deleted_by_at')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'source_rule_key']);
            $table->index(['tenant_id', 'contract_id', 'deleted_at']);
            $table->foreign(['tenant_id', 'contract_id'])->references(['tenant_id', 'id'])->on('contracts')->restrictOnDelete();
        });

        Schema::create('contract_generation_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('contract_id');
            $table->string('source_key');
            $table->foreignId('suppressed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('suppressed_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_key']);
            $table->index(['tenant_id', 'contract_id']);
            $table->foreign(['tenant_id', 'contract_id'])->references(['tenant_id', 'id'])->on('contracts')->restrictOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'contract_id'])->references(['tenant_id', 'id'])->on('contracts')->restrictOnDelete();
        });

        Schema::table('expense_rows', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'source_key']);
            $table->uuid('contract_source_rule_key')->nullable()->after('contract_term_id');
            $table->date('contract_occurrence_date')->nullable()->after('contract_source_rule_key');
            $table->string('current_source_key')->nullable()->storedAs('CASE WHEN deleted_at IS NULL THEN source_key ELSE NULL END');
            $table->unique(['tenant_id', 'current_source_key']);
            $table->index(['tenant_id', 'contract_term_id'], 'expense_rows_tenant_term_idx');
            $table->foreign(['tenant_id', 'contract_term_id'])->references(['tenant_id', 'id'])->on('contract_terms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_rows', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'contract_term_id']);
            $table->dropIndex('expense_rows_tenant_term_idx');
            $table->dropUnique(['tenant_id', 'current_source_key']);
            $table->dropColumn(['contract_source_rule_key', 'contract_occurrence_date', 'current_source_key']);
            $table->index(['tenant_id', 'source_key']);
        });
        Schema::table('expenses', fn (Blueprint $table) => $table->dropForeign(['tenant_id', 'contract_id']));
        Schema::dropIfExists('contract_generation_exceptions');
        Schema::dropIfExists('contract_terms');
        Schema::dropIfExists('contracts');
    }
};
