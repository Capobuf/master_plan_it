<?php

namespace App\Domain\Budget\Services;

use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Data\ApprovalExclusion;
use App\Domain\Budget\Data\BudgetCompositionEvidence;
use App\Domain\Budget\Data\BudgetProposal;
use App\Domain\Budget\Data\BudgetSourceAccess;
use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class BudgetProposalComposer
{
    public function __construct(private BudgetProposalFingerprint $fingerprint) {}

    /**
     * @param  array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}|null  $sourceMetadata
     */
    public function compose(
        AnnualEconomicProjection $projection,
        int $budgetLockVersion,
        BudgetSourceAccess $sourceAccess,
        ?array $sourceMetadata = null,
    ): BudgetProposal {
        $metadata = $sourceMetadata ?? $this->loadSourceMetadata($projection);
        $contributors = $this->contributors($projection, $metadata, $sourceAccess);
        usort($contributors, static fn (ApprovalContributor $left, ApprovalContributor $right): int => strcmp(
            $left->sourceIdentity,
            $right->sourceIdentity,
        ));
        $total = EconomicMeasure::zero($projection->basis);
        foreach ($contributors as $contributor) {
            $total = $total->plus($contributor->amount, $projection->basis);
        }
        $this->assertMeasureEquals($projection->currentPlanning, $total);

        $exclusions = $this->exclusions($projection, $metadata, $sourceAccess);
        usort($exclusions, static fn (ApprovalExclusion $left, ApprovalExclusion $right): int => strcmp(
            $left->sourceIdentity,
            $right->sourceIdentity,
        ));
        $fingerprint = $this->fingerprint->fingerprint(
            $projection->tenantId,
            $projection->planningYearId,
            $projection->currency,
            $projection->basis,
            $contributors,
        );

        return new BudgetProposal(
            tenantId: $projection->tenantId,
            planningYearId: $projection->planningYearId,
            yearLabel: $projection->economicYearLabel,
            currency: $projection->currency,
            basis: $projection->basis,
            composition: new BudgetCompositionEvidence(
                fingerprint: $fingerprint,
                budgetLockVersion: $budgetLockVersion,
                contributorCount: count($contributors),
            ),
            total: $total,
            contributors: $contributors,
            exclusions: $exclusions,
        );
    }

    /**
     * @param  array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}  $metadata
     * @return list<ApprovalContributor>
     */
    private function contributors(AnnualEconomicProjection $projection, array $metadata, BudgetSourceAccess $sourceAccess): array
    {
        $contributors = [];
        $identities = [];
        foreach ($projection->lines as $line) {
            if ($line->expenseKind !== 'ordinary' || ! $line->contributesToCurrentPlanning) {
                continue;
            }
            if (! in_array($line->type, ['estimate', 'quote'], true)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $expense = $metadata['expenses'][$line->expenseId] ?? null;
            $row = $metadata['rows'][$line->rowId] ?? null;
            if (! is_array($expense) || ! is_array($row)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $contributor = $this->contributor(
                $line,
                $expense,
                $row,
                ApprovalContributorKind::OrdinaryCurrentPlanning,
                $line->amount,
                (int) $row['lock_version'],
                $this->ordinaryNavigationAuthorized($expense, $row, $sourceAccess),
            );
            $this->assertUniqueIdentity($identities, $contributor->sourceIdentity);
            $contributors[] = $contributor;
        }

        foreach ($projection->plafonds as $plafond) {
            $expense = $metadata['expenses'][$plafond->plafondExpenseId] ?? null;
            if (! is_array($expense)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $contributor = new ApprovalContributor(
                sourceIdentity: 'plafond-allocation:'.$plafond->plafondExpenseId,
                kind: ApprovalContributorKind::PlafondAllocation,
                sourceLockVersion: (int) $expense['lock_version'],
                expenseId: $plafond->plafondExpenseId,
                expenseTitle: (string) $expense['title'],
                expenseKind: (string) $expense['kind'],
                rowId: null,
                rowType: null,
                rowDescription: null,
                costCenterId: (int) $expense['cost_center_id'],
                costCenterName: (string) $expense['cost_center_name'],
                vendorId: null,
                vendorName: null,
                projectId: $this->nullableInt($expense['project_id']),
                projectTitle: $this->nullableString($expense['project_title']),
                contractId: $this->nullableInt($expense['contract_id']),
                contractTitle: $this->nullableString($expense['contract_title']),
                amount: $plafond->allocation,
                drillDownAuthorized: $this->plafondNavigationAuthorized($expense, $sourceAccess),
                drillDownHref: $this->plafondNavigationAuthorized($expense, $sourceAccess)
                    ? '/api/v1/plafonds/'.$plafond->plafondExpenseId
                    : null,
            );
            $this->assertUniqueIdentity($identities, $contributor->sourceIdentity);
            $contributors[] = $contributor;
        }

        return $contributors;
    }

    /**
     * @param  array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}  $metadata
     * @return list<ApprovalExclusion>
     */
    private function exclusions(AnnualEconomicProjection $projection, array $metadata, BudgetSourceAccess $sourceAccess): array
    {
        $exclusions = [];
        $projectedRowIds = [];
        foreach ($projection->lines as $line) {
            $projectedRowIds[$line->rowId] = true;
            if ($line->expenseKind !== 'ordinary' || $line->contributesToCurrentPlanning) {
                continue;
            }
            $reason = match (true) {
                $line->type === 'actual' => 'actual_not_proposed',
                $line->contributesToCoveragePlanned => 'covered_by_plafond',
                in_array($line->type, ['estimate', 'quote'], true) && ! $line->isCurrentPlanning => 'alternative_planning',
                default => 'non_current_planning',
            };
            $expense = $metadata['expenses'][$line->expenseId] ?? null;
            $row = $metadata['rows'][$line->rowId] ?? null;
            if (! is_array($expense) || ! is_array($row)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $exclusions[] = $this->exclusion(
                $line,
                $expense,
                $row,
                $reason,
                $line->amount,
                $this->ordinaryNavigationAuthorized($expense, $row, $sourceAccess),
            );
        }

        foreach ($metadata['rows'] as $row) {
            if (isset($projectedRowIds[(int) $row['id']])
                || ($row['deleted_at'] ?? null) === null && ($row['expense_deleted_at'] ?? null) === null) {
                continue;
            }
            $expense = $metadata['expenses'][(int) $row['expense_id']] ?? null;
            if (! is_array($expense)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $measure = EconomicMeasure::fromAmounts(
                (string) $row['net_amount'],
                (string) $row['vat_amount'],
                (string) $row['gross_amount'],
                $projection->basis,
            );
            $exclusions[] = new ApprovalExclusion(
                sourceIdentity: 'expense-row:'.$row['id'],
                reason: 'soft_deleted',
                expenseId: null,
                expenseTitle: null,
                rowId: null,
                rowType: null,
                rowDescription: null,
                amount: $measure,
                detail: $this->detail('soft_deleted'),
                drillDownAuthorized: false,
                drillDownHref: null,
            );
        }

        return $exclusions;
    }

    /**
     * @param  array<string, mixed>  $expense
     * @param  array<string, mixed>  $row
     */
    private function contributor(
        ProjectedEconomicLine $line,
        array $expense,
        array $row,
        ApprovalContributorKind $kind,
        EconomicMeasure $amount,
        int $sourceLockVersion,
        bool $authorized,
    ): ApprovalContributor {
        return new ApprovalContributor(
            sourceIdentity: 'expense-row:'.$line->rowId,
            kind: $kind,
            sourceLockVersion: $sourceLockVersion,
            expenseId: $line->expenseId,
            expenseTitle: (string) $expense['title'],
            expenseKind: (string) $expense['kind'],
            rowId: $line->rowId,
            rowType: $line->type,
            rowDescription: (string) $row['description'],
            costCenterId: (int) $expense['cost_center_id'],
            costCenterName: (string) $expense['cost_center_name'],
            vendorId: $this->nullableInt($row['vendor_id']),
            vendorName: $this->nullableString($row['vendor_name']),
            projectId: $this->nullableInt($expense['project_id']),
            projectTitle: $this->nullableString($expense['project_title']),
            contractId: $this->nullableInt($expense['contract_id']),
            contractTitle: $this->nullableString($expense['contract_title']),
            amount: $amount,
            drillDownAuthorized: $authorized,
            drillDownHref: $authorized ? '/api/v1/expenses/'.$line->expenseId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $expense
     * @param  array<string, mixed>  $row
     */
    private function exclusion(
        ProjectedEconomicLine $line,
        array $expense,
        array $row,
        string $reason,
        EconomicMeasure $amount,
        bool $authorized,
    ): ApprovalExclusion {
        return new ApprovalExclusion(
            sourceIdentity: 'expense-row:'.$line->rowId,
            reason: $reason,
            expenseId: $line->expenseId,
            expenseTitle: (string) $expense['title'],
            rowId: $line->rowId,
            rowType: $line->type,
            rowDescription: (string) $row['description'],
            amount: $amount,
            detail: $this->detail($reason),
            drillDownAuthorized: $authorized,
            drillDownHref: $authorized ? '/api/v1/expenses/'.$line->expenseId : null,
        );
    }

    private function detail(string $reason): string
    {
        return match ($reason) {
            'alternative_planning' => 'Una sola pianificazione corrente per Spesa contribuisce alla proposta.',
            'actual_not_proposed' => 'Gli Effettivi non fanno parte del Budget Proposto.',
            'covered_by_plafond' => 'La pianificazione è informativa perché coperta dal Plafond.',
            'soft_deleted' => 'La sorgente eliminata non contribuisce alla proposta corrente.',
            default => 'La pianificazione non corrente non contribuisce alla proposta.',
        };
    }

    /** @return array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>} */
    private function loadSourceMetadata(AnnualEconomicProjection $projection): array
    {
        $records = DB::table('expense_rows')
            ->join('expenses', fn ($join) => $join->on('expenses.id', '=', 'expense_rows.expense_id')
                ->on('expenses.tenant_id', '=', 'expense_rows.tenant_id'))
            ->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')
                ->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('vendors', fn ($join) => $join->on('vendors.id', '=', 'expense_rows.vendor_id')
                ->on('vendors.tenant_id', '=', 'expense_rows.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')
                ->on('projects.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('contracts', fn ($join) => $join->on('contracts.id', '=', 'expenses.contract_id')
                ->on('contracts.tenant_id', '=', 'expenses.tenant_id'))
            ->where('expenses.tenant_id', $projection->tenantId)
            ->where('expenses.planning_year_id', $projection->planningYearId)
            ->orderBy('expense_rows.id')
            ->get([
                'expenses.id as expense_id', 'expenses.title as expense_title', 'expenses.kind as expense_kind',
                'expenses.lock_version as expense_lock_version', 'expenses.cost_center_id',
                'cost_centers.id as cost_center_record_id', 'cost_centers.name as cost_center_name',
                'expenses.project_id', 'projects.id as project_record_id', 'projects.title as project_title',
                'expenses.contract_id', 'contracts.id as contract_record_id', 'contracts.title as contract_title',
                'expenses.deleted_at as expense_deleted_at',
                'cost_centers.deleted_at as cost_center_deleted_at', 'vendors.deleted_at as vendor_deleted_at',
                'projects.deleted_at as project_deleted_at', 'contracts.deleted_at as contract_deleted_at',
                'expense_rows.id as row_id', 'expense_rows.lock_version as row_lock_version', 'expense_rows.type',
                'expense_rows.description', 'expense_rows.vendor_id', 'vendors.id as vendor_record_id', 'vendors.name as vendor_name',
                'expense_rows.net_amount', 'expense_rows.vat_amount', 'expense_rows.gross_amount',
                'expense_rows.deleted_at',
            ]);
        $expenses = [];
        $rows = [];
        foreach ($records as $record) {
            $expenseId = (int) $record->expense_id;
            $expenses[$expenseId] = [
                'id' => $expenseId,
                'title' => (string) $record->expense_title,
                'kind' => (string) $record->expense_kind,
                'lock_version' => (int) $record->expense_lock_version,
                'cost_center_id' => (int) $record->cost_center_id,
                'cost_center_exists' => $record->cost_center_record_id !== null,
                'cost_center_name' => (string) $record->cost_center_name,
                'project_id' => $record->project_id,
                'project_exists' => $record->project_record_id !== null,
                'project_title' => $record->project_title,
                'contract_id' => $record->contract_id,
                'contract_exists' => $record->contract_record_id !== null,
                'contract_title' => $record->contract_title,
                'deleted_at' => $record->expense_deleted_at,
                'cost_center_deleted_at' => $record->cost_center_deleted_at,
                'project_deleted_at' => $record->project_deleted_at,
                'contract_deleted_at' => $record->contract_deleted_at,
            ];
            $rows[(int) $record->row_id] = [
                'id' => (int) $record->row_id,
                'expense_id' => $expenseId,
                'lock_version' => (int) $record->row_lock_version,
                'type' => (string) $record->type,
                'description' => (string) $record->description,
                'vendor_id' => $record->vendor_id,
                'vendor_exists' => $record->vendor_id === null || $record->vendor_record_id !== null,
                'vendor_name' => $record->vendor_name,
                'net_amount' => (string) $record->net_amount,
                'vat_amount' => (string) $record->vat_amount,
                'gross_amount' => (string) $record->gross_amount,
                'deleted_at' => $record->deleted_at,
                'expense_deleted_at' => $record->expense_deleted_at,
                'vendor_deleted_at' => $record->vendor_deleted_at,
            ];
        }

        return ['expenses' => $expenses, 'rows' => $rows];
    }

    private function assertMeasureEquals(EconomicMeasure $expected, EconomicMeasure $actual): void
    {
        foreach (['net', 'vat', 'gross', 'official'] as $component) {
            if (bccomp($expected->{$component}, $actual->{$component}, 2) !== 0) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
        }
    }

    /** @param array<string, mixed> $expense */
    private function plafondNavigationAuthorized(array $expense, BudgetSourceAccess $access): bool
    {
        return $access->expense
            && $access->planningYear
            && $access->costCenter
            && ($expense['cost_center_exists'] ?? false)
            && ($expense['deleted_at'] ?? null) === null
            && ($expense['cost_center_deleted_at'] ?? null) === null;
    }

    /**
     * @param  array<string, mixed>  $expense
     * @param  array<string, mixed>  $row
     */
    private function ordinaryNavigationAuthorized(array $expense, array $row, BudgetSourceAccess $access): bool
    {
        return $this->plafondNavigationAuthorized($expense, $access)
            && $access->vendor
            && ($row['vendor_exists'] ?? false)
            && (($expense['project_id'] ?? null) === null || ($access->project && ($expense['project_exists'] ?? false)))
            && (($expense['contract_id'] ?? null) === null || ($access->contract && ($expense['contract_exists'] ?? false)))
            && ($row['deleted_at'] ?? null) === null
            && ($row['expense_deleted_at'] ?? null) === null
            && ($row['vendor_deleted_at'] ?? null) === null
            && ($expense['project_deleted_at'] ?? null) === null
            && ($expense['contract_deleted_at'] ?? null) === null;
    }

    /** @param array<string, true> $identities */
    private function assertUniqueIdentity(array &$identities, string $identity): void
    {
        if (isset($identities[$identity])) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }

        $identities[$identity] = true;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
