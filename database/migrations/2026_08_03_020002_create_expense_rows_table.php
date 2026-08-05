<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('position');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->enum('type', ['estimate', 'quote', 'actual']);
            $table->enum('confirmation_state', ['to_confirm', 'confirmed'])->nullable();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->boolean('is_system_managed')->default(false);
            $table->timestamp('manual_override_at')->nullable();
            $table->unsignedBigInteger('contract_term_id')->nullable();
            $table->string('source_key')->nullable();
            $table->string('description');
            $table->decimal('quantity', 19, 6)->nullable();
            $table->decimal('unit_price', 19, 6)->nullable();
            $table->decimal('entered_amount', 19, 6);
            $table->boolean('amount_includes_vat');
            $table->decimal('vat_rate', 12, 6);
            $table->decimal('net_amount', 19, 2);
            $table->decimal('vat_amount', 19, 2);
            $table->decimal('gross_amount', 19, 2);
            $table->boolean('is_extra');
            $table->unsignedBigInteger('funded_plafond_expense_id')->nullable();
            $table->date('spend_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->enum('distribution', ['all', 'start', 'end'])->nullable();
            $table->string('external_reference')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'source_key']);
            $table->index(['tenant_id', 'expense_id', 'deleted_at'], 'expense_rows_tenant_expense_del_idx');
            $table->index(['tenant_id', 'vendor_id', 'deleted_at'], 'expense_rows_tenant_vendor_del_idx');
            $table->index(['tenant_id', 'funded_plafond_expense_id', 'deleted_at'], 'expense_rows_tenant_plafond_del_idx');

            $table->foreign(['tenant_id', 'expense_id'])
                ->references(['tenant_id', 'id'])
                ->on('expenses')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'vendor_id'])
                ->references(['tenant_id', 'id'])
                ->on('vendors')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'funded_plafond_expense_id'])
                ->references(['tenant_id', 'id'])
                ->on('expenses')
                ->restrictOnDelete();
            $table->foreign('confirmed_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `expense_rows` ADD CONSTRAINT `expense_rows_extra_funding_xor` CHECK (NOT (is_extra = 1 AND funded_plafond_expense_id IS NOT NULL))');
        DB::statement('ALTER TABLE `expense_rows` ADD CONSTRAINT `expense_rows_date_shape_spend` CHECK (NOT (spend_date IS NOT NULL AND (period_start IS NOT NULL OR period_end IS NOT NULL OR distribution IS NOT NULL)))');
        DB::statement('ALTER TABLE `expense_rows` ADD CONSTRAINT `expense_rows_date_shape_period` CHECK (NOT (spend_date IS NULL AND distribution IS NULL))');
        DB::statement('ALTER TABLE `expense_rows` ADD CONSTRAINT `expense_rows_date_shape_complete` CHECK (NOT (distribution IS NOT NULL AND (period_start IS NULL OR period_end IS NULL)))');
        DB::statement('ALTER TABLE `expense_rows` ADD CONSTRAINT `expense_rows_confirmation_matrix` CHECK ((type <> \'actual\' AND confirmation_state IS NULL AND confirmed_by_user_id IS NULL AND confirmed_at IS NULL) OR (type = \'actual\' AND confirmation_state <=> \'to_confirm\' AND confirmed_by_user_id IS NULL AND confirmed_at IS NULL) OR (type = \'actual\' AND confirmation_state <=> \'confirmed\' AND confirmed_by_user_id IS NOT NULL AND confirmed_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_rows');
    }
};
