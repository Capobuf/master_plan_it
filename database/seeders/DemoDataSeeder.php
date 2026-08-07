<?php

namespace Database\Seeders;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Contracts\Actions\ResumeContractOccurrence;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Expenses\Actions\ConfirmActual;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
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
            $tenant = Tenant::query()->updateOrCreate(['code' => 'demo'], ['name' => 'Azienda Demo S.r.l.', 'state' => TenantState::Active, 'currency_code' => 'EUR', 'language_code' => 'it', 'timezone' => 'Europe/Rome', 'default_vat_rate' => '22.000000', 'budget_basis' => BudgetBasis::Net, 'created_by_user_id' => $administrator->getKey()]);
            $year = (int) now('Europe/Rome')->year;
            foreach ([$year - 1, $year, $year + 1] as $label) {
                PlanningYear::query()->updateOrCreate(['tenant_id' => $tenant->id, 'year_label' => $label], ['active' => $label === $year]);
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
            $plafond = Expense::query()->updateOrCreate(['tenant_id' => $tenant->id, 'title' => 'DEMO — Security annual Plafond'], ['planning_year_id' => $years[$year], 'cost_center_id' => $costs[3]->id, 'kind' => ExpenseKind::Plafond, 'notes' => 'Deterministic demo Plafond', 'project_id' => null, 'contract_id' => null]);
            $this->row($tenant, $plafond, $administrator, 1, null, ExpenseType::Estimate, 'Security allocation', '50000.000000', "{$year}-01-15", null, false);
            for ($i = 1; $i <= 24; $i++) {
                $label = $i <= 6 ? $year - 1 : $year;
                $type = match ($i % 4) {
                    0 => ExpenseType::Estimate,1 => ExpenseType::Quote,default => ExpenseType::Actual
                };
                $expense = Expense::query()->updateOrCreate(['tenant_id' => $tenant->id, 'title' => sprintf('DEMO — Expense %02d', $i)], ['planning_year_id' => $years[$label], 'cost_center_id' => $costs[$i % count($costs)]->id, 'kind' => ExpenseKind::Ordinary, 'notes' => 'Deterministic local demo expense', 'project_id' => null, 'contract_id' => null]);
                $amount = $i === 23 ? '-125.000000' : number_format(500 + $i * 73, 6, '.', '');
                $period = $this->demoPeriod($label, $i);
                $this->row($tenant, $expense, $administrator, 1, $vendors[$i % count($vendors)]->id, $type, 'Demo expense row', $amount, $period ? null : sprintf('%04d-%02d-15', $label, ($i % 12) + 1), $period, false, $label === $year && $i % 5 === 0 ? $plafond->id : null, $i % 7 === 0);
                if ($i % 6 === 0) {
                    $this->row($tenant, $expense, $administrator, 2, $vendors[($i + 1) % count($vendors)]->id, ExpenseType::Quote, 'Additional demo row', '250.000000', sprintf('%04d-%02d-20', $label, ($i % 12) + 1), null, false);
                }
            }
            $context = new TenantContext($tenant, $administrator);
            for ($i = 0; $i < 6; $i++) {
                $title = sprintf('DEMO — Contract %02d', $i + 1);
                $contract = Contract::query()->where('tenant_id', $tenant->id)->where('title', $title)->first();
                if (! $contract) {
                    $start = CarbonImmutable::create($year, 1 + $i, 1);
                    $end = $i === 4 ? now()->addDays(25) : CarbonImmutable::create($year, 12, 31);
                    $terms = $i === 5 ? [new SaveContractTermData(null, 'demo-term-5a', CarbonImmutable::create($year, 6, 1)->toDateString(), CarbonImmutable::create($year, 8, 31)->toDateString(), BillingCycle::Monthly, null, null, '1800.000000', false, '22.000000', false, null), new SaveContractTermData(null, 'demo-term-5b', CarbonImmutable::create($year, 9, 1)->toDateString(), CarbonImmutable::create($year, 12, 31)->toDateString(), BillingCycle::Monthly, null, null, '2100.000000', false, '22.000000', false, null)] : [new SaveContractTermData(null, 'demo-term-'.$i, $start->toDateString(), $end->toDateString(), $i % 2 === 0 ? BillingCycle::Monthly : BillingCycle::Annual, null, null, number_format(900 + $i * 250, 6, '.', ''), false, '22.000000', $i === 1, null)];
                    $data = new SaveContractData($vendors[$i]->id, $costs[$i % 5]->id, $title, 'Deterministic demo contract', true, $i === 4 ? $end->toDateString() : null, $i === 4 ? 30 : null, $i === 1 ? 'Auto-renew demo' : null, null, $terms);
                    $contract = app(CreateContract::class)->execute($administrator, $context, $data, $this->correlation());
                }
                app(SynchronizeContractOccurrences::class)->execute($administrator, $context, $contract, $this->correlation());
            }
            $first = Contract::query()->where('tenant_id', $tenant->id)->where('title', 'DEMO — Contract 01')->firstOrFail();
            $occ = app(ExpectedContractOccurrenceQuery::class)->forContract($first, $year);
            if (isset($occ[0]) && $occ[0]->expenseId) {
                $expense = Expense::query()->findOrFail($occ[0]->expenseId);
                $row = $expense->rows()->first();
                if ($row && $row->confirmation_state === ActualConfirmationState::ToConfirm) {
                    app(ConfirmActual::class)->execute($administrator, $context, $expense, $row, $row->lock_version, $this->correlation());
                }
            }
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
        }));
    }

    /** @return array{start: string, end: string, distribution: string}|null */
    private function demoPeriod(int $label, int $index): ?array
    {
        if ($index % 3 !== 0) {
            return null;
        }

        return [
            'start' => sprintf('%04d-%02d-01', $label, ($index % 10) + 1),
            'end' => sprintf('%04d-%02d-28', $label, min(12, ($index % 10) + 3)),
            'distribution' => ['all', 'start', 'end'][intdiv($index, 3) % 3],
        ];
    }

    /** @param array{start: string, end: string, distribution: string}|null $period */
    private function row(Tenant $tenant, Expense $expense, User $actor, int $position, ?int $vendorId, ExpenseType $type, string $description, string $entered, ?string $spend, ?array $period, bool $system, ?int $funded = null, bool $extra = false): void
    {
        $net = bcdiv($entered, '1', 2);
        $vat = bcdiv(bcmul($entered, '0.22', 6), '1', 2);
        $gross = bcadd($net, $vat, 2);
        $actual = $type === ExpenseType::Actual;
        ExpenseRow::query()->updateOrCreate(['tenant_id' => $tenant->id, 'expense_id' => $expense->id, 'position' => $position], ['vendor_id' => $vendorId, 'type' => $type, 'confirmation_state' => $actual ? ActualConfirmationState::Confirmed : null, 'confirmed_by_user_id' => $actual ? $actor->id : null, 'confirmed_at' => $actual ? now('UTC') : null, 'is_system_managed' => $system, 'description' => $description, 'quantity' => null, 'unit_price' => null, 'entered_amount' => $entered, 'amount_includes_vat' => false, 'vat_rate' => '22.000000', 'net_amount' => $net, 'vat_amount' => $vat, 'gross_amount' => $gross, 'is_extra' => $extra, 'funded_plafond_expense_id' => $funded, 'spend_date' => $spend, 'period_start' => $period['start'] ?? null, 'period_end' => $period['end'] ?? null, 'distribution' => $period['distribution'] ?? null, 'external_reference' => 'DEMO']);
    }

    private function correlation(): string
    {
        return (string) Str::uuid();
    }
}
