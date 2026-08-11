<?php

namespace App\Domain\Contracts\Queries;

use App\Domain\Revisions\Data\RevisionDiff;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\ContractPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

final class ContractRevisionQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function history(User $actor, TenantContext $context, Contract $contract): Collection
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $contract)->authorize();
        $canRestore = $policy->restoreRevision($actor, $contract)->allowed();

        return app(OperationalRevisionQuery::class)->visibleForRoot($context, $contract)
            ->map(fn (RevisionBatch $batch): array => $this->metadata($batch, $canRestore));
    }

    public function sourceBatchForRestore(User $actor, TenantContext $context, Contract $contract, int $revisionId): RevisionBatch
    {
        $this->policy($context)->restoreRevision($actor, $contract)->authorize();

        return app(OperationalRevisionQuery::class)->findVisibleBatch($context, $contract, $revisionId);
    }

    /** @return array<string, mixed> */
    public function comparison(User $actor, TenantContext $context, Contract $contract, int $revisionId): array
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $contract)->authorize();
        $logical = app(OperationalRevisionQuery::class);
        $batch = $logical->findVisibleBatch($context, $contract, $revisionId);
        $state = $logical->snapshot($context, $contract, $batch);
        $changes = $this->diff($context, $contract, $state);
        $canRestore = $changes !== [] && $policy->restoreRevision($actor, $contract)->allowed();

        return [
            'revision' => $this->metadata($batch, $canRestore),
            'changes' => array_map(static fn (RevisionDiff $diff): array => $diff->toArray(), $changes),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $state
     * @return list<RevisionDiff>
     */
    private function diff(TenantContext $context, Contract $contract, array $state): array
    {
        $snapshot = $state[$contract->getMorphClass()][(int) $contract->getKey()];
        $fields = [
            'title' => ['Titolo', $snapshot['title'] ?? null, $contract->title],
            'description' => ['Descrizione', $snapshot['description'] ?? null, $contract->description],
            'vendor' => ['Fornitore', $this->label(Vendor::class, $context, $snapshot['vendor_id'] ?? null, 'name'), $this->label(Vendor::class, $context, $contract->vendor_id, 'name')],
            'cost_center' => ['Centro di costo', $this->label(CostCenter::class, $context, $snapshot['cost_center_id'] ?? null, 'name'), $this->label(CostCenter::class, $context, $contract->cost_center_id, 'name')],
            'project' => ['Progetto', $this->label(Project::class, $context, $snapshot['project_id'] ?? null, 'title'), $this->label(Project::class, $context, $contract->project_id, 'title')],
            'active' => ['Stato', ($snapshot['active'] ?? false) ? 'Attivo' : 'Inattivo', $contract->active ? 'Attivo' : 'Inattivo'],
            'renewal_date' => ['Data rinnovo', $snapshot['renewal_date'] ?? null, $contract->renewal_date?->toDateString()],
            'renewal_notice_days' => ['Preavviso rinnovo', $snapshot['renewal_notice_days'] ?? null, $contract->renewal_notice_days],
            'renewal_notes' => ['Note rinnovo', $snapshot['renewal_notes'] ?? null, $contract->renewal_notes],
        ];
        $changes = [];
        foreach ($fields as $field => [$label, $old, $now]) {
            if ($this->different($old, $now)) {
                $changes[] = new RevisionDiff('contract', 'Contratto', $field, $label, $old, $now);
            }
        }

        $type = app(ContractTerm::class)->getMorphClass();
        $revisionTerms = $state[$type] ?? [];
        $currentTerms = $contract->terms()->get()->keyBy(fn (ContractTerm $term): int => (int) $term->getKey());
        $ids = collect(array_keys($revisionTerms))->merge($currentTerms->keys())->unique()->sort()->values();
        foreach ($ids as $id) {
            $revision = $revisionTerms[(int) $id] ?? null;
            $current = $currentTerms->get((int) $id);
            $subject = $this->termSubject($revision, $current);
            if (! is_array($revision) || ! $current instanceof ContractTerm) {
                $changes[] = new RevisionDiff('contract_term', $subject, 'presence', 'Presenza', is_array($revision) ? 'Presente' : 'Assente', $current instanceof ContractTerm ? 'Presente' : 'Assente');

                continue;
            }
            $termFields = [
                'effective_start' => ['Inizio', $revision['effective_start'] ?? null, $current->effective_start?->toDateString()],
                'effective_end' => ['Fine', $revision['effective_end'] ?? null, $current->effective_end?->toDateString()],
                'billing_cycle' => ['Periodicità', $revision['billing_cycle'] ?? null, $current->getRawOriginal('billing_cycle')],
                'quantity' => ['Quantità', $revision['quantity'] ?? null, $current->quantity],
                'unit_price' => ['Prezzo unitario', $revision['unit_price'] ?? null, $current->unit_price],
                'entered_amount' => ['Importo inserito', $revision['entered_amount'] ?? null, $current->entered_amount],
                'vat_rate' => ['Aliquota IVA', $revision['vat_rate'] ?? null, $current->vat_rate],
                'gross_amount' => ['Importo lordo', $revision['gross_amount'] ?? null, $current->gross_amount],
                'auto_renew' => ['Rinnovo automatico', ($revision['auto_renew'] ?? false) ? 'Sì' : 'No', $current->auto_renew ? 'Sì' : 'No'],
            ];
            foreach ($termFields as $field => [$label, $old, $now]) {
                if ($this->different($old, $now, in_array($field, ['quantity', 'unit_price', 'entered_amount', 'vat_rate', 'gross_amount'], true))) {
                    $changes[] = new RevisionDiff('contract_term', $subject, $field, $label, $old, $now);
                }
            }
        }

        return $changes;
    }

    /** @param array<string, mixed>|null $revision */
    private function termSubject(?array $revision, ?ContractTerm $current): string
    {
        $start = $revision['effective_start'] ?? $current?->effective_start?->toDateString() ?? '—';
        $end = $revision['effective_end'] ?? $current?->effective_end?->toDateString() ?? '—';

        return "Termine {$start}–{$end}";
    }

    /** @return array<string, mixed> */
    private function metadata(RevisionBatch $batch, bool $canRestore): array
    {
        $operation = $batch->operation instanceof RevisionOperation ? $batch->operation->value : (string) $batch->getRawOriginal('operation');
        $changedItems = $batch->items->where('is_changed', true);
        $termCount = $changedItems->where('versionable_type', app(ContractTerm::class)->getMorphClass())->count();

        return [
            'id' => (int) $batch->getKey(),
            'operation' => $operation,
            'actor' => ['kind' => $batch->visualActorKind()->value, 'label' => $batch->visualActorLabel()],
            'timestamp' => $batch->occurred_at?->toIso8601String(),
            'summary' => $batch->reason ?? match ($operation) {
                'create' => 'Creazione contratto', 'restore' => 'Ripristino contratto', 'delete' => 'Eliminazione contratto', default => 'Aggiornamento contratto',
            },
            'changed_count' => $changedItems->count(),
            'changed_fields' => $termCount > 0
                ? ['Contratto', $termCount === 1 ? '1 termine' : "{$termCount} termini"]
                : ['Contratto'],
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
        $value = TenantOwnedRecordQuery::forTenant($context, $model)
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey((int) $id)
            ->value($column);

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

    private function policy(TenantContext $context): ContractPolicy
    {
        return new ContractPolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }
}
