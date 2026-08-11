<?php

namespace App\Domain\Contracts\Data;

use App\Models\Contract;
use App\Models\ContractTerm;
use DateTimeInterface;

final readonly class ContractOccurrenceKey
{
    public function __construct(public string $value) {}

    public static function make(Contract $contract, ContractTerm $term, int $planningYear, DateTimeInterface $date): self
    {
        return new self(hash('sha256', implode(':', [
            (int) $contract->tenant_id,
            (int) $contract->getKey(),
            (string) $term->source_rule_key,
            $planningYear,
            $date->format('Y-m-d'),
        ])));
    }

    public static function annual(Contract $contract, int $planningYear): self
    {
        return new self(hash('sha256', implode(':', [
            (int) $contract->tenant_id,
            (int) $contract->getKey(),
            'annual-planning',
            $planningYear,
        ])));
    }
}
