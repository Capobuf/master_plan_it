<?php

namespace App\Domain\Budget\Data;

final readonly class BudgetCompositionEvidence
{
    public const PROJECTION_VERSION = 'annual-economic-projection/v1';

    public const SCHEMA_VERSION = 'budget-proposal-composition/v1';

    public function __construct(
        public string $fingerprint,
        public int $budgetLockVersion,
        public int $contributorCount,
        public string $schemaVersion = self::SCHEMA_VERSION,
        public string $projectionVersion = self::PROJECTION_VERSION,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'fingerprint' => $this->fingerprint,
            'versions' => [
                'budget_lock_version' => $this->budgetLockVersion,
                'projection_version' => $this->projectionVersion,
            ],
            'contributor_count' => $this->contributorCount,
        ];
    }
}
