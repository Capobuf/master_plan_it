<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use DomainException;

final class DeleteGeneratedExpense
{
    public function execute(User $actor, TenantContext $context, Expense $expense, int $expectedLockVersion, bool $allowRegeneration, string $correlationId): void
    {
        if ($expense->contract_id === null || ! $expense->rows()->whereNotNull('source_key')->exists()) { throw new DomainException('INVALID_OCCURRENCE'); }
        app(DeleteExpense::class)->execute($actor, $context, $expense, $expectedLockVersion, ! $allowRegeneration, $correlationId);
    }
}
