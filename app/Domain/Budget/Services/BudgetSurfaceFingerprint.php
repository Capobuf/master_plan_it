<?php

namespace App\Domain\Budget\Services;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use JsonException;
use Normalizer;

final class BudgetSurfaceFingerprint
{
    public const SCHEMA_VERSION = 'budget-current-surface/v1';

    /**
     * @param  array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}  $metadata
     *
     * @throws JsonException
     */
    public function fingerprint(
        AnnualEconomicProjection $projection,
        int $budgetLockVersion,
        string $budgetState,
        ?string $economicBaseLockedAt,
        array $metadata,
    ): string {
        $lines = $projection->lines;
        usort($lines, static fn (ProjectedEconomicLine $left, ProjectedEconomicLine $right): int => $left->rowId <=> $right->rowId);
        $expenses = array_values($metadata['expenses']);
        $rows = array_values($metadata['rows']);
        usort($expenses, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);
        usort($rows, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);

        $payload = [
            'basis' => $projection->basis,
            'budget_lock_version' => $budgetLockVersion,
            'budget_state' => $budgetState,
            'currency' => $projection->currency,
            'economic_base_locked_at' => $economicBaseLockedAt,
            'expenses' => $expenses,
            'lines' => array_map(fn (ProjectedEconomicLine $line): array => $this->line($line), $lines),
            'planning_year_id' => $projection->planningYearId,
            'rows' => $rows,
            'schema_version' => self::SCHEMA_VERSION,
            'tenant_id' => $projection->tenantId,
            'year_label' => $projection->economicYearLabel,
        ];

        return 'sha256:'.hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @return array<string, mixed> */
    private function line(ProjectedEconomicLine $line): array
    {
        return [
            'amount' => $this->measure($line->amount),
            'contract_id' => $line->contractId,
            'contributes_to_consumed' => $line->contributesToConsumed,
            'contributes_to_coverage_planned' => $line->contributesToCoveragePlanned,
            'contributes_to_current_planning' => $line->contributesToCurrentPlanning,
            'cost_center_id' => $line->costCenterId,
            'cost_center_name' => $line->costCenterName,
            'description' => $line->description,
            'expense_id' => $line->expenseId,
            'expense_kind' => $line->expenseKind,
            'expense_title' => $line->expenseTitle,
            'funded_plafond_expense_id' => $line->fundedPlafondExpenseId,
            'funded_plafond_title' => $line->fundedPlafondTitle,
            'is_current_planning' => $line->isCurrentPlanning,
            'notes' => $line->notes,
            'plafond_cost_center_id' => $line->plafondCostCenterId,
            'plafond_cost_center_name' => $line->plafondCostCenterName,
            'project_id' => $line->projectId,
            'row_id' => $line->rowId,
            'spend_date' => $line->spendDate,
            'type' => $line->type,
            'vendor_id' => $line->vendorId,
            'vendor_name' => $line->vendorName,
        ];
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return [
            'net' => bcadd($measure->net, '0', 2),
            'vat' => bcadd($measure->vat, '0', 2),
            'gross' => bcadd($measure->gross, '0', 2),
            'official' => bcadd($measure->official, '0', 2),
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
