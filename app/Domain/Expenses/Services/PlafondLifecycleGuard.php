<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Budget\Enums\BudgetState;
use DomainException;

final class PlafondLifecycleGuard
{
    public function assertPreparation(BudgetState|string $state): void
    {
        $value = $state instanceof BudgetState ? $state->value : $state;

        if ($value !== BudgetState::Preparation->value) {
            throw new DomainException('BUDGET_STATE_CONFLICT');
        }
    }
}
