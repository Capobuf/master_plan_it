<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Models\Tenant;
use App\Models\User;

final readonly class TenantContext
{
    public int $tenantId;

    public string $languageCode;

    public string $timezone;

    public string $currencyCode;

    public string $defaultVatRate;

    public BudgetBasis $budgetBasis;

    public function __construct(
        public Tenant $tenant,
        public User $actor,
    ) {
        $this->tenantId = (int) $tenant->getKey();
        $this->languageCode = (string) $tenant->language_code;
        $this->timezone = (string) $tenant->timezone;
        $this->currencyCode = (string) $tenant->currency_code;
        $this->defaultVatRate = (string) $tenant->default_vat_rate;
        $rawBudgetBasis = $tenant->getRawOriginal('budget_basis');
        if ($rawBudgetBasis === null) {
            $rawBudgetBasis = $tenant->getAttribute('budget_basis');
        }

        $this->budgetBasis = $rawBudgetBasis instanceof BudgetBasis
            ? $rawBudgetBasis
            : BudgetBasis::from((string) $rawBudgetBasis);
    }
}
