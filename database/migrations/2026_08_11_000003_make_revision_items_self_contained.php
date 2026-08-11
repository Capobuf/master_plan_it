<?php

use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revision_batches', function (Blueprint $table): void {
            $table->enum('actor_kind', ['human', 'system'])->default('human')->after('actor_user_id');
        });

        Schema::table('revision_batch_items', function (Blueprint $table): void {
            $table->json('snapshot_contents')->nullable()->after('versionable_id');
            $table->string('operational_root_type')->nullable()->after('snapshot_contents');
            $table->unsignedBigInteger('operational_root_id')->nullable()->after('operational_root_type');
            $table->index(
                ['tenant_id', 'operational_root_type', 'operational_root_id', 'revision_batch_id'],
                'revision_items_operational_root_idx',
            );
            $table->foreignId('version_id')->nullable()->change();
        });

        $this->backfillItems();
        $this->backfillRestoreSources();

        $missingSnapshots = DB::table('revision_batch_items')->whereNull('snapshot_contents')->count();
        if ($missingSnapshots !== 0) {
            throw new RuntimeException("REVISION_SNAPSHOT_BACKFILL_INCOMPLETE: {$missingSnapshots}");
        }

        Schema::table('revision_batch_items', function (Blueprint $table): void {
            $table->json('snapshot_contents')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('revision_batch_items')->whereNull('version_id')->exists()) {
            throw new RuntimeException('REVISION_SCHEMA_ROLLBACK_UNSAFE');
        }

        Schema::table('revision_batch_items', function (Blueprint $table): void {
            $table->dropIndex('revision_items_operational_root_idx');
            $table->dropColumn(['snapshot_contents', 'operational_root_type', 'operational_root_id']);
            $table->foreignId('version_id')->nullable(false)->change();
        });

        Schema::table('revision_batches', function (Blueprint $table): void {
            $table->dropColumn('actor_kind');
        });
    }

    private function backfillItems(): void
    {
        DB::table('revision_batch_items as items')
            ->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->join('versions', 'versions.id', '=', 'items.version_id')
            ->select([
                'items.id',
                'items.versionable_type',
                'items.versionable_id',
                'batches.tenant_id',
                'versions.contents',
            ])
            ->orderBy('items.id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    [$rootType, $rootId] = $this->operationalRoot(
                        (string) $item->versionable_type,
                        (int) $item->versionable_id,
                        (string) $item->contents,
                    );

                    DB::table('revision_batch_items')->where('id', $item->id)->update([
                        'tenant_id' => (int) $item->tenant_id,
                        'snapshot_contents' => (string) $item->contents,
                        'operational_root_type' => $rootType,
                        'operational_root_id' => $rootId,
                    ]);
                }
            }, 'items.id', 'id');
    }

    private function backfillRestoreSources(): void
    {
        DB::table('revision_batches')
            ->where('operation', 'restore')
            ->whereNull('restored_from_batch_id')
            ->whereNotNull('restored_from_version_id')
            ->orderBy('id')
            ->chunkById(250, function ($batches): void {
                foreach ($batches as $batch) {
                    $candidateIds = DB::table('revision_batch_items as items')
                        ->join('revision_batches as source_batches', 'source_batches.id', '=', 'items.revision_batch_id')
                        ->where('items.version_id', $batch->restored_from_version_id)
                        ->where('source_batches.tenant_id', $batch->tenant_id)
                        ->where('items.operational_root_type', $batch->root_subject_type)
                        ->where('items.operational_root_id', $batch->root_subject_id)
                        ->distinct()
                        ->pluck('items.revision_batch_id');

                    if ($candidateIds->count() === 1) {
                        DB::table('revision_batches')->where('id', $batch->id)->update([
                            'restored_from_batch_id' => $candidateIds->first(),
                        ]);
                    }
                }
            });
    }

    /** @return array{string|null, int|null} */
    private function operationalRoot(string $type, int $id, string $rawContents): array
    {
        $contents = json_decode($rawContents, true, 512, JSON_THROW_ON_ERROR);

        if ($type === (new Expense)->getMorphClass()) {
            return [(new Expense)->getMorphClass(), $id];
        }
        if ($type === (new ExpenseRow)->getMorphClass()) {
            $expenseId = $contents['expense_id'] ?? DB::table('expense_rows')->where('id', $id)->value('expense_id');

            return $expenseId === null ? [null, null] : [(new Expense)->getMorphClass(), (int) $expenseId];
        }
        if ($type === (new Contract)->getMorphClass()) {
            return [(new Contract)->getMorphClass(), $id];
        }
        if ($type === (new ContractTerm)->getMorphClass()) {
            $contractId = $contents['contract_id'] ?? DB::table('contract_terms')->where('id', $id)->value('contract_id');

            return $contractId === null ? [null, null] : [(new Contract)->getMorphClass(), (int) $contractId];
        }
        foreach ([Project::class, Vendor::class, CostCenter::class] as $model) {
            $morph = (new $model)->getMorphClass();
            if ($type === $morph) {
                return [$morph, $id];
            }
        }
        if ($type === (new PlanningYear)->getMorphClass()) {
            return [null, null];
        }

        return [null, null];
    }
};
