<?php

namespace App\Domain\Economics\Data;

final readonly class EconomicDataset
{
    /** @param list<EconomicLine> $lines */
    public function __construct(public EconomicScope $scope, public array $lines) {}
}
