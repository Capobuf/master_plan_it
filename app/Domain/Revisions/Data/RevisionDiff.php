<?php

namespace App\Domain\Revisions\Data;

final readonly class RevisionDiff
{
    public function __construct(
        public string $scope,
        public string $subject,
        public string $field,
        public string $label,
        public mixed $revisionValue,
        public mixed $currentValue,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'subject' => $this->subject,
            'field' => $this->field,
            'label' => $this->label,
            'revision_value' => $this->revisionValue,
            'current_value' => $this->currentValue,
        ];
    }
}
