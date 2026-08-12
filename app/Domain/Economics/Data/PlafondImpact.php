<?php

namespace App\Domain\Economics\Data;

final readonly class PlafondImpact
{
    /** @param list<ProjectedEconomicLine> $blockingRows */
    public function __construct(
        public PlafondEconomicProjection $current,
        public PlafondEconomicProjection $proposed,
        public string $requested,
        public string $shortage,
        public bool $canConfirm,
        public array $blockingRows,
    ) {}
}
