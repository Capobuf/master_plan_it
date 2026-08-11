<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Revisions\Data\RevisionDiff;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

final class ExpenseRevisionQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function history(User $actor, TenantContext $context, Expense $expense): Collection
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $expense)->authorize();
        $canRestore = $policy->restoreRevision($actor, $expense)->allowed();

        return app(OperationalRevisionQuery::class)->visibleForRoot($context, $expense)
            ->map(fn (RevisionBatch $batch): array => $this->metadata($batch, $canRestore));
    }

    public function sourceBatchForRestore(User $actor, TenantContext $context, Expense $expense, int $revisionId): RevisionBatch
    {
        $this->policy($context)->restoreRevision($actor, $expense)->authorize();

        return app(OperationalRevisionQuery::class)->findVisibleBatch($context, $expense, $revisionId);
    }

    /** @return array<string, mixed> */
    public function comparison(User $actor, TenantContext $context, Expense $expense, int $revisionId): array
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $expense)->authorize();
        $logical = app(OperationalRevisionQuery::class);
        $batch = $logical->findVisibleBatch($context, $expense, $revisionId);
        $state = $logical->snapshot($context, $expense, $batch);
        $changes = $this->diff($context, $expense, $state);
        $canRestore = $changes !== [] && $policy->restoreRevision($actor, $expense)->allowed();

        return [
            'revision' => $this->metadata($batch, $canRestore),
            'changes' => array_map(static fn (RevisionDiff $diff): array => $diff->toArray(), $changes),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $state
     * @return list<RevisionDiff>
     */
    private function diff(TenantContext $context, Expense $expense, array $state): array
    {
        $snapshot = $state[$expense->getMorphClass()][(int) $expense->getKey()];
        $rootFields = [
            'title' => ['Titolo', (string) ($snapshot['title'] ?? ''), (string) $expense->title],
            'notes' => ['Note', $snapshot['notes'] ?? null, $expense->notes],
            'kind' => ['Tipologia', $snapshot['kind'] ?? null, $expense->getRawOriginal('kind')],
            'planning_year' => ['Anno', $this->label(PlanningYear::class, $context, $snapshot['planning_year_id'] ?? null, 'year_label'), $this->label(PlanningYear::class, $context, $expense->planning_year_id, 'year_label')],
            'cost_center' => ['Centro di costo', $this->label(CostCenter::class, $context, $snapshot['cost_center_id'] ?? null, 'name'), $this->label(CostCenter::class, $context, $expense->cost_center_id, 'name')],
            'project' => ['Progetto', $this->label(Project::class, $context, $snapshot['project_id'] ?? null, 'title'), $this->label(Project::class, $context, $expense->project_id, 'title')],
            'contract' => ['Contratto', $this->label(Contract::class, $context, $snapshot['contract_id'] ?? null, 'title'), $this->label(Contract::class, $context, $expense->contract_id, 'title')],
        ];
        $changes = [];
        foreach ($rootFields as $field => [$label, $revision, $current]) {
            if ($this->different($revision, $current)) {
                $changes[] = new RevisionDiff('expense', 'Spesa', $field, $label, $revision, $current);
            }
        }

        $rowType = app(ExpenseRow::class)->getMorphClass();
        $revisionRows = $state[$rowType] ?? [];
        $currentRows = $expense->rows()->get()->keyBy(fn (ExpenseRow $row): int => (int) $row->getKey());
        $ids = collect(array_keys($revisionRows))->merge($currentRows->keys())->unique()->sort()->values();
        foreach ($ids as $id) {
            $revision = $revisionRows[(int) $id] ?? null;
            $current = $currentRows->get((int) $id);
            $description = is_array($revision) ? ($revision['description'] ?? null) : null;
            if ($description === null && $current instanceof ExpenseRow) {
                $description = $current->description;
            }
            $subject = 'Riga '.($description ?? (string) $id);
            if (! is_array($revision) || ! $current instanceof ExpenseRow) {
                $changes[] = new RevisionDiff(
                    'expense_row',
                    $subject,
                    'presence',
                    'Presenza',
                    is_array($revision) ? 'Presente' : 'Assente',
                    $current instanceof ExpenseRow ? 'Presente' : 'Assente',
                );

                continue;
            }
            $fields = [
                'description' => ['Descrizione', $revision['description'] ?? null, $current->description],
                'vendor' => ['Fornitore', $this->label(Vendor::class, $context, $revision['vendor_id'] ?? null, 'name'), $this->label(Vendor::class, $context, $current->vendor_id, 'name')],
                'type' => ['Tipo riga', $revision['type'] ?? null, $current->getRawOriginal('type')],
                'quantity' => ['Quantità', $revision['quantity'] ?? null, $current->quantity],
                'unit_price' => ['Prezzo unitario', $revision['unit_price'] ?? null, $current->unit_price],
                'entered_amount' => ['Importo inserito', $revision['entered_amount'] ?? null, $current->entered_amount],
                'vat_rate' => ['Aliquota IVA', $revision['vat_rate'] ?? null, $current->vat_rate],
                'spend_date' => ['Data spesa', $revision['spend_date'] ?? null, $current->spend_date],
                'external_reference' => ['Riferimento esterno', $revision['external_reference'] ?? null, $current->external_reference],
            ];
            foreach ($fields as $field => [$label, $old, $now]) {
                if ($this->different($old, $now, in_array($field, ['quantity', 'unit_price', 'entered_amount', 'vat_rate'], true))) {
                    $changes[] = new RevisionDiff('expense_row', $subject, $field, $label, $old, $now);
                }
            }
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private function metadata(RevisionBatch $batch, bool $canRestore): array
    {
        $operation = $batch->operation instanceof RevisionOperation ? $batch->operation->value : (string) $batch->getRawOriginal('operation');
        $changedItems = $batch->items->where('is_changed', true);
        $rowCount = $changedItems->where('versionable_type', app(ExpenseRow::class)->getMorphClass())->count();

        return [
            'id' => (int) $batch->getKey(),
            'operation' => $operation,
            'actor' => ['kind' => $batch->visualActorKind()->value, 'label' => $batch->visualActorLabel()],
            'timestamp' => $batch->occurred_at?->toIso8601String(),
            'summary' => $batch->reason ?? match ($operation) {
                'create' => 'Creazione spesa', 'restore' => 'Ripristino spesa', 'delete' => 'Eliminazione spesa', default => 'Aggiornamento spesa',
            },
            'changed_count' => $changedItems->count(),
            'changed_fields' => $rowCount > 0
                ? ['Spesa', $rowCount === 1 ? '1 riga' : "{$rowCount} righe"]
                : ['Spesa'],
            'can_compare' => true,
            'can_restore' => $canRestore,
        ];
    }

    /** @param class-string $model */
    private function label(string $model, TenantContext $context, mixed $id, string $column): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }
        $query = TenantOwnedRecordQuery::forTenant($context, $model)->whereKey((int) $id);
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }
        $value = $query->value($column);

        return $value === null ? 'Riferimento non più disponibile' : (string) $value;
    }

    private function normalized(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $value;
    }

    private function different(mixed $revision, mixed $current, bool $decimal = false): bool
    {
        $revision = $this->normalized($revision);
        $current = $this->normalized($current);
        if (! $decimal || $revision === null || $current === null) {
            return $revision !== $current;
        }

        $revisionValue = (string) $revision;
        $currentValue = (string) $current;
        if (preg_match('/^-?\d+(?:\.\d+)?$/D', $revisionValue) !== 1
            || preg_match('/^-?\d+(?:\.\d+)?$/D', $currentValue) !== 1) {
            return $revisionValue !== $currentValue;
        }

        return bccomp($revisionValue, $currentValue, max($this->decimalScale($revisionValue), $this->decimalScale($currentValue))) !== 0;
    }

    private function decimalScale(string $value): int
    {
        $separator = strpos($value, '.');

        return $separator === false ? 0 : strlen($value) - $separator - 1;
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }
}
