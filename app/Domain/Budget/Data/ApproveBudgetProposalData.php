<?php

namespace App\Domain\Budget\Data;

final readonly class ApproveBudgetProposalData
{
    public function __construct(
        public string $effectiveDate,
        public ?string $note,
        public string $compositionSchemaVersion,
        public string $compositionFingerprint,
        public int $budgetLockVersion,
        public string $projectionVersion,
    ) {}

    public static function fromEvidence(
        string $effectiveDate,
        ?string $note,
        BudgetCompositionEvidence $evidence,
    ): self {
        $normalizedNote = $note === null ? null : trim($note);

        return new self(
            effectiveDate: $effectiveDate,
            note: $normalizedNote === '' ? null : $normalizedNote,
            compositionSchemaVersion: $evidence->schemaVersion,
            compositionFingerprint: $evidence->fingerprint,
            budgetLockVersion: $evidence->budgetLockVersion,
            projectionVersion: $evidence->projectionVersion,
        );
    }
}
