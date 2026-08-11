<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'expense_rows' => ['quantity', 'unit_price', 'entered_amount', 'vat_rate'],
            'contract_terms' => ['quantity', 'unit_price', 'entered_amount', 'vat_rate'],
            'tenants' => ['default_vat_rate'],
        ];
        $invalid = [];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $count = DB::table($table)
                    ->whereNotNull($column)
                    ->whereRaw("`{$column}` <> TRUNCATE(`{$column}`, 2)")
                    ->count();

                if ($count > 0) {
                    $invalid[] = "{$table}.{$column} ({$count} rows)";
                }
            }
        }

        if ($invalid !== []) {
            throw new RuntimeException(
                'Cannot enforce two-decimal schema: significant digits beyond scale 2 found in '.implode(', ', $invalid).'.',
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `expense_rows`
                MODIFY `quantity` DECIMAL(19,2) NULL,
                MODIFY `unit_price` DECIMAL(19,2) NULL,
                MODIFY `entered_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `vat_rate` DECIMAL(12,2) NOT NULL,
                MODIFY `net_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `vat_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `gross_amount` DECIMAL(19,2) NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `contract_terms`
                MODIFY `quantity` DECIMAL(19,2) NULL,
                MODIFY `unit_price` DECIMAL(19,2) NULL,
                MODIFY `entered_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `vat_rate` DECIMAL(12,2) NOT NULL,
                MODIFY `net_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `vat_amount` DECIMAL(19,2) NOT NULL,
                MODIFY `gross_amount` DECIMAL(19,2) NOT NULL
            SQL);
        DB::statement('ALTER TABLE `tenants` MODIFY `default_vat_rate` DECIMAL(12,2) NOT NULL');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `expense_rows`
                MODIFY `quantity` DECIMAL(19,6) NULL,
                MODIFY `unit_price` DECIMAL(19,6) NULL,
                MODIFY `entered_amount` DECIMAL(19,6) NOT NULL,
                MODIFY `vat_rate` DECIMAL(12,6) NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `contract_terms`
                MODIFY `quantity` DECIMAL(19,6) NULL,
                MODIFY `unit_price` DECIMAL(19,6) NULL,
                MODIFY `entered_amount` DECIMAL(19,6) NOT NULL,
                MODIFY `vat_rate` DECIMAL(12,6) NOT NULL
            SQL);
        DB::statement('ALTER TABLE `tenants` MODIFY `default_vat_rate` DECIMAL(12,6) NOT NULL');
    }
};
