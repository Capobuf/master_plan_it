<?php

namespace App\Livewire\Expenses;

use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.operational')]
final class ExpenseIndex extends Component
{
    public ?int $planningYearId = null;

    /** @var list<array{id: int, year: int, cost_center: string, kind: string, title: string, row_count: int, net: string, vat: string, gross: string}> */
    public array $rows = [];

    /** @var array{net: string, vat: string, gross: string} */
    public array $totals = ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];

    /** @var list<array{id: int, label: int, active: bool}> */
    public array $years = [];

    public int $page = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public ?string $errorCode = null;

    public ?string $correlationId = null;

    public function mount(): void
    {
        $this->loadRegister();
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->loadRegister();
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
        $this->loadRegister();
    }

    public function nextPage(): void
    {
        $this->page = min($this->lastPage, $this->page + 1);
        $this->loadRegister();
    }

    public function reloadRegister(): void
    {
        $this->loadRegister();
    }

    public function render(): View
    {
        return view('livewire.expenses.expense-index');
    }

    private function loadRegister(): void
    {
        $this->errorCode = null;
        $this->correlationId = null;

        try {
            $actor = auth()->user();

            if (! $actor instanceof User) {
                throw new AuthorizationException('PERMISSION_DENIED');
            }

            $context = app(TenantContext::class);
            $query = app(ExpenseRegisterQuery::class);
            $page = $query->paginate($actor, $context, $this->planningYearId, $this->page, 15);

            $this->rows = array_map(fn (ExpenseRegisterRow $row): array => [
                'id' => $row->id,
                'year' => $row->planningYearLabel,
                'cost_center' => $row->costCenterName,
                'kind' => $row->kind,
                'title' => $row->title,
                'row_count' => $row->rowCount,
                'net' => $row->netTotal,
                'vat' => $row->vatTotal,
                'gross' => $row->grossTotal,
            ], $page->items());
            $this->totals = $query->totals($actor, $context, $this->planningYearId);
            $this->years = $query->yearOptions($actor, $context);
            $this->total = $page->total();
            $this->lastPage = max(1, $page->lastPage());
            $this->page = min($this->page, $this->lastPage);
        } catch (AuthorizationException) {
            $this->clearDataset();
            $this->errorCode = 'PERMISSION_DENIED';
        } catch (Throwable $exception) {
            report($exception);
            $this->clearDataset();
            $this->errorCode = 'UNEXPECTED_ERROR';
            $this->correlationId = CorrelationId::resolveFor(request())->value();
        }
    }

    private function clearDataset(): void
    {
        $this->rows = [];
        $this->totals = ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
        $this->total = 0;
        $this->lastPage = 1;
        $this->page = 1;
    }
}
