<?php

namespace App\Domain\Economics\Data;

final readonly class EconomicSummary
{
    /** @param array<string,string> $amounts */
    public function __construct(public string $officialBasis, public array $amounts) {}
}
