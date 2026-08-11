<?php

namespace App\Domain\Contracts\Data;

final readonly class SaveContractData
{
    /** @param list<SaveContractTermData> $terms */
    public function __construct(
        public int $vendorId,
        public int $costCenterId,
        public string $title,
        public ?string $description,
        public bool $active,
        public ?string $renewalDate,
        public ?int $renewalNoticeDays,
        public ?string $renewalNotes,
        public ?int $expectedLockVersion,
        public array $terms,
        public ?int $projectId = null,
    ) {}
}
