<?php

namespace Database\Seeders;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Contracts\Actions\ResumeContractOccurrence;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\PromoteDeferredProjects;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Media;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo data cannot be seeded in production.');
        }
        $administrator = User::query()->whereNull('tenant_id')->where('is_active', true)->get()->first(fn (User $u) => app(PlatformAdministrator::class)->hasProtectedRole($u));
        if (! $administrator instanceof User) {
            throw new RuntimeException('An existing active Platform Administrator is required to seed demo data.');
        }

        Model::unguarded(fn () => DB::transaction(function () use ($administrator): void {
            $tenant = Tenant::query()->updateOrCreate(['code' => 'demo'], ['name' => 'Azienda Demo S.r.l.', 'state' => TenantState::Active, 'currency_code' => 'EUR', 'language_code' => 'it', 'timezone' => 'Europe/Rome', 'default_vat_rate' => '22.00', 'budget_basis' => 'net', 'attachment_quota_bytes' => '2147483648', 'created_by_user_id' => $administrator->getKey()]);
            Tenant::query()->updateOrCreate(['code' => 'demo-gross'], ['name' => 'Azienda Demo Lordo S.r.l.', 'state' => TenantState::Active, 'currency_code' => 'EUR', 'language_code' => 'it', 'timezone' => 'Europe/Rome', 'default_vat_rate' => '22.00', 'budget_basis' => 'gross', 'attachment_quota_bytes' => '2147483648', 'created_by_user_id' => $administrator->getKey()]);
            $year = (int) now('Europe/Rome')->year;
            foreach ([$year - 1, $year, $year + 1] as $label) {
                PlanningYear::query()->updateOrCreate(['tenant_id' => $tenant->id, 'year_label' => $label], ['active' => $label >= $year]);
            }
            $costNames = ['Infrastructure', 'Cloud & Connectivity', 'Software & Licenses', 'Security', 'End User Devices'];
            $costs = [];
            foreach ($costNames as $name) {
                $costs[] = CostCenter::query()->updateOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['active' => true]);
            }
            $vendorNames = ['DemoNet Italia', 'Nuvola Demo S.p.A.', 'Licenze Demo S.r.l.', 'Sicurezza Demo Group', 'Device Demo Store', 'Datacenter Demo Italia', 'Consulenza Demo Tech', 'Telecom Demo Lab'];
            $vendors = [];
            foreach ($vendorNames as $i => $name) {
                $vendors[] = Vendor::query()->updateOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['vat_number' => 'IT'.str_pad((string) (90000000000 + $i), 11, '0', STR_PAD_LEFT), 'email' => 'demo-vendor-'.($i + 1).'@example.test', 'active' => true]);
            }
            $years = PlanningYear::query()->where('tenant_id', $tenant->id)->pluck('id', 'year_label');
            $context = new TenantContext($tenant, $administrator);
            $projects = $this->projects($tenant, $administrator, $context, $costs, $years->all(), $year);
            for ($i = 1; $i <= 24; $i++) {
                $label = $i <= 6 ? $year - 1 : $year;
                $type = match ($i % 4) {
                    0 => ExpenseType::Estimate,1 => ExpenseType::Quote,default => ExpenseType::Actual
                };
                $expense = Expense::query()->firstOrNew(['tenant_id' => $tenant->id, 'title' => sprintf('DEMO — Expense %02d', $i)]);
                $expenseIsNew = ! $expense->exists;
                $expense->fill([
                    'planning_year_id' => $years[$label],
                    'cost_center_id' => $costs[$i % count($costs)]->id,
                    'kind' => ExpenseKind::Ordinary,
                    'notes' => $i === 12 && $expense->exists ? 'Storico demo: versione corrente' : 'Deterministic local demo expense',
                    'project_id' => $i % 3 === 0 ? $projects[$i % count($projects)]->id : null,
                    'contract_id' => null,
                ])->save();
                $amount = $i === 23 ? '-125.00' : number_format(500 + $i * 73, 2, '.', '');
                $spendDate = $type === ExpenseType::Actual
                    ? sprintf('%04d-%02d-15', $label, ($i % 12) + 1)
                    : null;
                $this->row($tenant, $expense, $administrator, 1, $vendors[$i % count($vendors)]->id, $type, 'Demo expense row', $amount, $spendDate, null, false);
                if ($i % 6 === 0) {
                    $this->row($tenant, $expense, $administrator, 2, $vendors[($i + 1) % count($vendors)]->id, ExpenseType::Quote, $i === 12 && ! $expenseIsNew ? 'Riga aggiuntiva aggiornata' : 'Additional demo row', '250.00', null, null, false);
                }
            }
            $this->canonicalPlafondDataset($tenant, $administrator, $costs, $vendors, $years->all(), $year);
            $outOfYearActual = Expense::query()->updateOrCreate(['tenant_id' => $tenant->id, 'title' => 'DEMO — Actual fuori anno'], ['planning_year_id' => $years[$year], 'cost_center_id' => $costs[0]->id, 'kind' => ExpenseKind::Ordinary, 'notes' => 'Actual negativo con Data reale indipendente']);
            $this->row($tenant, $outOfYearActual, $administrator, 1, $vendors[0]->id, ExpenseType::Actual, 'Rimborso fuori anno', '-5.00', '2026-02-10', null, false);
            $this->expenseHistory($tenant, $administrator, $context);
            for ($i = 0; $i < 6; $i++) {
                $title = sprintf('DEMO — Contract %02d', $i + 1);
                $contract = Contract::query()->where('tenant_id', $tenant->id)->where('title', $title)->first();
                if (! $contract) {
                    $start = CarbonImmutable::create($year, 1 + $i, 1);
                    $end = $i === 4 ? now()->addDays(25) : CarbonImmutable::create($year, 12, 31);
                    $terms = $i === 5 ? [new SaveContractTermData(null, 'demo-term-5a', CarbonImmutable::create($year, 6, 1)->toDateString(), CarbonImmutable::create($year, 8, 31)->toDateString(), BillingCycle::Monthly, null, null, '1800.00', false, '22.00', false, null), new SaveContractTermData(null, 'demo-term-5b', CarbonImmutable::create($year, 9, 1)->toDateString(), CarbonImmutable::create($year, 12, 31)->toDateString(), BillingCycle::Monthly, null, null, '2100.00', false, '22.00', false, null)] : [new SaveContractTermData(null, 'demo-term-'.$i, $start->toDateString(), $end->toDateString(), $i % 2 === 0 ? BillingCycle::Monthly : BillingCycle::Annual, null, null, number_format(900 + $i * 250, 2, '.', ''), false, '22.00', $i === 1, null)];
                    $data = new SaveContractData($vendors[$i]->id, $costs[$i % 5]->id, $title, 'Deterministic demo contract', true, $i === 4 ? $end->toDateString() : null, $i === 4 ? 30 : null, $i === 1 ? 'Auto-renew demo' : null, null, $terms, $projects[$i % count($projects)]->id);
                    $contract = app(CreateContract::class)->execute($administrator, $context, $data, $this->correlation());
                }
                if ($i === 0 && $contract->description === 'Deterministic demo contract') {
                    $contract = $this->updateFirstContract($administrator, $context, $contract);
                }
                app(SynchronizeContractOccurrences::class)->execute($administrator, $context, $contract, $this->correlation());
            }
            $first = Contract::query()->where('tenant_id', $tenant->id)->where('title', 'DEMO — Contract 01')->firstOrFail();
            $occ = app(ExpectedContractOccurrenceQuery::class)->forContract($first, $year);
            if (isset($occ[1]) && $occ[1]->expenseId) {
                $expense = Expense::query()->with('rows')->findOrFail($occ[1]->expenseId);
                $row = $expense->rows->first();
                if ($row && $row->is_system_managed) {
                    $kind = $expense->kind instanceof ExpenseKind ? $expense->kind : ExpenseKind::from((string) $expense->getRawOriginal('kind'));
                    $type = $row->type instanceof ExpenseType ? $row->type : ExpenseType::from((string) $row->getRawOriginal('type'));
                    $edata = new SaveExpenseData((int) $expense->planning_year_id, (int) $expense->cost_center_id, $kind, $expense->title, $expense->notes, null, (int) $expense->contract_id, (int) $expense->lock_version);
                    $rdata = new SaveExpenseRowData((int) $row->id, 1, (int) $row->vendor_id, $type, $row->description.' (manual override)', $row->quantity === null ? null : (string) $row->quantity, $row->unit_price === null ? null : (string) $row->unit_price, (string) $row->entered_amount, (bool) $row->amount_includes_vat, (string) $row->vat_rate, false, null, (string) $row->spend_date, null, null, null, $row->external_reference, (int) $row->lock_version);
                    app(UpdateExpense::class)->execute($administrator, $context, $expense, $edata, [$rdata], $this->correlation());
                }
            }
            if (isset($occ[2]) && $occ[2]->expenseId) {
                $expense = Expense::query()->findOrFail($occ[2]->expenseId);
                app(DeleteGeneratedExpense::class)->execute($administrator, $context, $expense, (int) $expense->lock_version, false, $this->correlation());
            }
            if (isset($occ[3]) && $occ[3]->expenseId) {
                $expense = Expense::query()->findOrFail($occ[3]->expenseId);
                app(DeleteGeneratedExpense::class)->execute($administrator, $context, $expense, (int) $expense->lock_version, false, $this->correlation());
                app(ResumeContractOccurrence::class)->execute($administrator, $context, $first, $occ[3]->sourceKey, $this->correlation());
            }
            $attachmentExpense = Expense::query()->with('rows')->where('tenant_id', $tenant->id)->where('title', 'DEMO — Expense 12')->firstOrFail();
            $this->demoAttachment($tenant, $administrator, $attachmentExpense, 'Spesa-demo.pdf');
            $this->demoAttachment($tenant, $administrator, $attachmentExpense->rows->firstOrFail(), 'Riga-spesa-demo.pdf');
            $this->demoAttachment($tenant, $administrator, $first, 'Contratto-demo.pdf');
            $this->demoAttachment($tenant, $administrator, $projects[0], 'Progetto-demo.pdf');
        }));
    }

    /**
     * @param  list<CostCenter>  $costs
     * @param  array<int, int>  $years
     * @return list<Project>
     */
    private function projects(Tenant $tenant, User $actor, TenantContext $context, array $costs, array $years, int $year): array
    {
        $draftTitle = 'DEMO — Migrazione Cloud (bozza)';
        $finalTitle = 'DEMO — Migrazione Cloud';
        $cloud = Project::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('title', [$draftTitle, $finalTitle])
            ->first();
        if (! $cloud instanceof Project) {
            $cloud = app(CreateProject::class)->execute(
                $actor,
                $context,
                new SaveProjectData($draftTitle, $costs[1]->id, ProjectStage::Idea, null, null),
                $this->correlation(),
            );
        }
        if ($cloud->title === $draftTitle) {
            $cloud = app(UpdateProject::class)->execute(
                $actor,
                $context,
                $cloud,
                new SaveProjectData($finalTitle, $costs[1]->id, ProjectStage::Approved, null, (int) $cloud->lock_version),
                $this->correlation(),
            );
        }

        $definitions = [
            ['DEMO — Rinnovo Endpoint', 4, ProjectStage::Proposed, null],
            ['DEMO — Potenziamento SOC', 3, ProjectStage::Idea, null],
            ['DEMO — Consolidamento Datacenter', 0, ProjectStage::Deferred, $years[$year + 1]],
            ['DEMO — ERP Modernization', 2, ProjectStage::Deferred, $years[$year]],
        ];
        $projects = [$cloud];
        foreach ($definitions as [$title, $costIndex, $stage, $targetYearId]) {
            $project = Project::query()->where('tenant_id', $tenant->id)->where('title', $title)->first();
            if (! $project instanceof Project) {
                $project = app(CreateProject::class)->execute(
                    $actor,
                    $context,
                    new SaveProjectData($title, $costs[$costIndex]->id, $stage, $targetYearId, null),
                    $this->correlation(),
                );
            }
            $projects[] = $project;
        }

        app(PromoteDeferredProjects::class)->execute($actor, $context);

        return array_map(
            fn (Project $project): Project => $project->fresh(['costCenter', 'deferredTargetPlanningYear']),
            $projects,
        );
    }

    private function expenseHistory(Tenant $tenant, User $actor, TenantContext $context): void
    {
        $expense = Expense::query()
            ->with('rows')
            ->where('tenant_id', $tenant->id)
            ->where('title', 'DEMO — Expense 12')
            ->firstOrFail();
        if ($expense->notes !== 'Deterministic local demo expense') {
            return;
        }

        $expense = $this->updateDemoExpense($actor, $context, $expense, 'Storico demo: prima versione', false);
        $this->updateDemoExpense($actor, $context, $expense, 'Storico demo: versione corrente', true);
    }

    private function updateDemoExpense(User $actor, TenantContext $context, Expense $expense, string $notes, bool $changeSecondRow): Expense
    {
        $expense = $expense->fresh('rows');
        $kind = $expense->kind instanceof ExpenseKind ? $expense->kind : ExpenseKind::from((string) $expense->getRawOriginal('kind'));
        $data = new SaveExpenseData(
            (int) $expense->planning_year_id,
            (int) $expense->cost_center_id,
            $kind,
            $expense->title,
            $notes,
            $expense->project_id === null ? null : (int) $expense->project_id,
            $expense->contract_id === null ? null : (int) $expense->contract_id,
            (int) $expense->lock_version,
            $expense->credit_for_expense_id === null ? null : (int) $expense->credit_for_expense_id,
        );
        $rows = $expense->rows->map(function (ExpenseRow $row) use ($changeSecondRow, $expense): SaveExpenseRowData {
            $type = $row->type instanceof ExpenseType ? $row->type : ExpenseType::from((string) $row->getRawOriginal('type'));
            $distribution = $row->distribution instanceof Distribution ? $row->distribution : ($row->distribution === null ? null : Distribution::from((string) $row->getRawOriginal('distribution')));

            return new SaveExpenseRowData(
                (int) $row->id,
                (int) $row->position,
                $row->vendor_id === null ? null : (int) $row->vendor_id,
                $type,
                $changeSecondRow && $row->position === 2 ? 'Riga aggiuntiva aggiornata' : $row->description,
                $row->quantity === null ? null : (string) $row->quantity,
                $row->unit_price === null ? null : (string) $row->unit_price,
                (string) $row->entered_amount,
                (bool) $row->amount_includes_vat,
                (string) $row->vat_rate,
                (bool) $row->is_extra,
                $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id,
                $row->spend_date === null ? null : (string) $row->spend_date,
                $row->period_start === null ? null : (string) $row->period_start,
                $row->period_end === null ? null : (string) $row->period_end,
                $distribution,
                $row->external_reference,
                (int) $row->lock_version,
                (int) $expense->current_planning_row_id === (int) $row->id,
            );
        })->all();

        return app(UpdateExpense::class)->execute($actor, $context, $expense, $data, $rows, $this->correlation());
    }

    private function updateFirstContract(User $actor, TenantContext $context, Contract $contract): Contract
    {
        $contract = $contract->fresh('terms');
        $terms = $contract->terms->map(function ($term, int $index): SaveContractTermData {
            $billingCycle = $term->billing_cycle instanceof BillingCycle ? $term->billing_cycle : BillingCycle::from((string) $term->getRawOriginal('billing_cycle'));

            return new SaveContractTermData(
                (int) $term->id,
                (string) $term->source_rule_key,
                $term->effective_start->toDateString(),
                $term->effective_end->toDateString(),
                $billingCycle,
                $term->quantity === null ? null : (string) $term->quantity,
                $term->unit_price === null ? null : (string) $term->unit_price,
                $index === 0 ? bcadd((string) $term->entered_amount, '100.00', 2) : (string) $term->entered_amount,
                (bool) $term->amount_includes_vat,
                (string) $term->vat_rate,
                (bool) $term->auto_renew,
                (int) $term->lock_version,
            );
        })->all();
        $data = new SaveContractData(
            (int) $contract->vendor_id,
            (int) $contract->cost_center_id,
            $contract->title,
            'Contratto demo aggiornato per lo storico operativo.',
            (bool) $contract->active,
            $contract->renewal_date?->toDateString(),
            $contract->renewal_notice_days === null ? null : (int) $contract->renewal_notice_days,
            $contract->renewal_notes,
            (int) $contract->lock_version,
            $terms,
            $contract->project_id === null ? null : (int) $contract->project_id,
        );

        return app(UpdateContract::class)->execute($actor, $context, $contract, $data, $this->correlation());
    }

    /**
     * @param  list<CostCenter>  $costs
     * @param  list<Vendor>  $vendors
     * @param  array<int, int>  $years
     */
    private function canonicalPlafondDataset(Tenant $tenant, User $actor, array $costs, array $vendors, array $years, int $year): void
    {
        $plafond = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'DEMO — Plafond Infrastructure'],
            [
                'planning_year_id' => $years[$year],
                'cost_center_id' => $costs[0]->id,
                'kind' => ExpenseKind::Plafond,
                'notes' => 'Dataset canonico Plafond 3000 + 1000 - 500',
                'project_id' => null,
                'contract_id' => null,
                'current_planning_row_id' => null,
            ],
        );

        foreach ([
            [1, 'Allocazione iniziale', '3000.00', sprintf('%04d-01-10', $year)],
            [2, 'Aumento allocazione', '1000.00', sprintf('%04d-02-10', $year)],
            [3, 'Riduzione allocazione', '-500.00', sprintf('%04d-03-10', $year)],
        ] as [$position, $description, $amount, $date]) {
            $this->allocationAdjustment($tenant, $plafond, $actor, $position, $description, $amount, $date);
        }

        $planned = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'DEMO — Pianificazione coperta Plafond'],
            [
                'planning_year_id' => $years[$year],
                'cost_center_id' => $costs[2]->id,
                'kind' => ExpenseKind::Ordinary,
                'notes' => 'Pianificazione coperta cross-centro di costo',
                'project_id' => null,
                'contract_id' => null,
            ],
        );
        $this->row($tenant, $planned, $actor, 1, $vendors[2]->id, ExpenseType::Quote, 'Pianificazione coperta 4200', '4200.00', null, null, false, $plafond->id);

        $actual = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'DEMO — Effettivo coperto Plafond'],
            [
                'planning_year_id' => $years[$year],
                'cost_center_id' => $costs[3]->id,
                'kind' => ExpenseKind::Ordinary,
                'notes' => 'Effettivo coperto cross-centro di costo',
                'project_id' => null,
                'contract_id' => null,
                'current_planning_row_id' => null,
            ],
        );
        $this->row($tenant, $actual, $actor, 1, $vendors[3]->id, ExpenseType::Actual, 'Effettivo coperto 1500', '1500.00', sprintf('%04d-04-15', $year), null, false, $plafond->id);
        $this->row($tenant, $actual, $actor, 2, $vendors[4]->id, ExpenseType::Actual, 'Effettivo coperto 1000', '1000.00', sprintf('%04d-05-15', $year), null, false, $plafond->id);
    }

    private function allocationAdjustment(Tenant $tenant, Expense $plafond, User $actor, int $position, string $description, string $amount, string $date): void
    {
        $net = bcdiv($amount, '1', 2);
        $vat = bcdiv(bcmul($amount, '0.22', 6), '1', 2);

        ExpenseRow::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'expense_id' => $plafond->id, 'position' => $position],
            [
                'vendor_id' => null,
                'type' => ExpenseType::AllocationAdjustment,
                'created_by_user_id' => $actor->id,
                'confirmation_state' => null,
                'confirmed_by_user_id' => null,
                'confirmed_at' => null,
                'is_system_managed' => false,
                'manual_override_at' => null,
                'contract_term_id' => null,
                'source_key' => null,
                'description' => $description,
                'notes' => 'Variazione allocazione demo attribuibile',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => $amount,
                'amount_includes_vat' => false,
                'vat_rate' => '22.00',
                'net_amount' => $net,
                'vat_amount' => $vat,
                'gross_amount' => bcadd($net, $vat, 2),
                'is_extra' => false,
                'funded_plafond_expense_id' => null,
                'spend_date' => $date,
                'period_start' => null,
                'period_end' => null,
                'distribution' => null,
                'external_reference' => null,
            ],
        );
    }

    /** @param array{start: string, end: string, distribution: string}|null $period */
    private function row(Tenant $tenant, Expense $expense, User $actor, int $position, ?int $vendorId, ExpenseType $type, string $description, string $entered, ?string $spend, ?array $period, bool $system, ?int $fundedPlafondExpenseId = null): void
    {
        $net = bcdiv($entered, '1', 2);
        $vat = bcdiv(bcmul($entered, '0.22', 6), '1', 2);
        $gross = bcadd($net, $vat, 2);
        $row = ExpenseRow::query()->updateOrCreate(['tenant_id' => $tenant->id, 'expense_id' => $expense->id, 'position' => $position], ['vendor_id' => $vendorId, 'type' => $type, 'created_by_user_id' => null, 'confirmation_state' => null, 'confirmed_by_user_id' => null, 'confirmed_at' => null, 'is_system_managed' => $system, 'description' => $description, 'notes' => 'Deterministic demo row', 'quantity' => null, 'unit_price' => null, 'entered_amount' => $entered, 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'net_amount' => $net, 'vat_amount' => $vat, 'gross_amount' => $gross, 'is_extra' => false, 'funded_plafond_expense_id' => $fundedPlafondExpenseId, 'spend_date' => $spend, 'period_start' => $period['start'] ?? null, 'period_end' => $period['end'] ?? null, 'distribution' => $period['distribution'] ?? null, 'external_reference' => 'DEMO']);

        if ($type !== ExpenseType::Actual && $expense->current_planning_row_id === null) {
            $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        }
    }

    private function demoAttachment(Tenant $tenant, User $actor, Expense|ExpenseRow|Contract|Project $parent, string $name): void
    {
        $exists = Media::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('model_type', $parent->getMorphClass())
            ->where('model_id', $parent->getKey())
            ->where('collection_name', 'attachments')
            ->where('name', $name)
            ->exists();
        if ($exists) {
            return;
        }

        $parent->addMediaFromString("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n")
            ->usingName($name)
            ->usingFileName((string) Str::uuid().'.pdf')
            ->withProperties([
                'tenant_id' => $tenant->getKey(),
                'uploaded_by_user_id' => $actor->getKey(),
            ])
            ->toMediaCollection('attachments', 'attachments');
    }

    private function correlation(): string
    {
        return (string) Str::uuid();
    }
}
