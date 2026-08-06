<?php

namespace App\Domain\Economics\Data;

use App\Domain\Tenancy\Enums\BudgetBasis;

final readonly class EconomicScope
{
    public function __construct(public int $tenantId, public int $planningYearId, public int $yearLabel, public string $currency, public BudgetBasis $budgetBasis) {}
}
