<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_rows', function (Blueprint $table): void {
            // These fields are Action-owned historical evidence.  They are deliberately
            // not fillable on ExpenseRow, so an editor cannot replace source provenance.
            $table->unsignedBigInteger('source_deleted_contract_id')->nullable()->after('source_key');
            $table->string('source_deleted_contract_title')->nullable()->after('source_deleted_contract_id');
            $table->timestamp('source_contract_deleted_at')->nullable()->after('source_deleted_contract_title');
            $table->text('source_contract_deletion_reason')->nullable()->after('source_contract_deleted_at');
            $table->unsignedBigInteger('source_deleted_term_id')->nullable()->after('source_contract_deletion_reason');
            $table->uuid('source_deleted_term_rule_key')->nullable()->after('source_deleted_term_id');
            $table->date('source_deleted_term_start')->nullable()->after('source_deleted_term_rule_key');
            $table->date('source_deleted_term_end')->nullable()->after('source_deleted_term_start');
            $table->timestamp('source_term_deleted_at')->nullable()->after('source_deleted_term_end');
            $table->text('source_term_deletion_reason')->nullable()->after('source_term_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('expense_rows', function (Blueprint $table): void {
            $table->dropColumn([
                'source_deleted_contract_id',
                'source_deleted_contract_title',
                'source_contract_deleted_at',
                'source_contract_deletion_reason',
                'source_deleted_term_id',
                'source_deleted_term_rule_key',
                'source_deleted_term_start',
                'source_deleted_term_end',
                'source_term_deleted_at',
                'source_term_deletion_reason',
            ]);
        });
    }
};
