<?php

namespace App\Domain\Projects\Data;

use App\Domain\Projects\Enums\ProjectStage;

final readonly class SaveProjectData
{
    public function __construct(
        public string $title,
        public int $costCenterId,
        public ProjectStage $stage,
        public ?int $deferredTargetPlanningYearId,
        public ?int $expectedLockVersion,
    ) {}
}
