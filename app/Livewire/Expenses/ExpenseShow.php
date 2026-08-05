<?php

namespace App\Livewire\Expenses;

use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.operational')]
final class ExpenseShow extends Component
{
    public int $expenseId;

    /** @var array{id: int, year: int, cost_center: string, kind: string, title: string, notes: ?string, rows: list<array<string, int|string|null>>, net: string, vat: string, gross: string}|null */
    public ?array $detail = null;

    public ?string $errorCode = null;

    public ?string $correlationId = null;

    public function mount(int $expense): void
    {
        $this->expenseId = $expense;
        $this->loadExpense();
    }

    public function reloadExpense(): void
    {
        $this->loadExpense();
    }

    public function render(): View
    {
        return view('livewire.expenses.expense-show');
    }

    private function loadExpense(): void
    {
        $this->errorCode = null;
        $this->correlationId = null;

        try {
            $actor = auth()->user();

            if (! $actor instanceof User) {
                throw new AuthorizationException('PERMISSION_DENIED');
            }

            $detail = app(ExpenseDetailQuery::class)->find(
                $actor,
                app(TenantContext::class),
                $this->expenseId,
            );

            $this->detail = [
                'id' => $detail->id,
                'year' => $detail->planningYearLabel,
                'cost_center' => $detail->costCenterName,
                'kind' => $detail->kind,
                'title' => $detail->title,
                'notes' => $detail->notes,
                'rows' => $detail->rows,
                'net' => $detail->netTotal,
                'vat' => $detail->vatTotal,
                'gross' => $detail->grossTotal,
            ];
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (AuthorizationException) {
            abort(404);
        } catch (Throwable $exception) {
            report($exception);
            $this->detail = null;
            $this->errorCode = 'UNEXPECTED_ERROR';
            $this->correlationId = CorrelationId::resolveFor(request())->value();
        }
    }
}
