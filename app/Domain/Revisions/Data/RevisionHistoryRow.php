<?php

namespace App\Domain\Revisions\Data;

final readonly class RevisionHistoryRow
{
    /**
     * @param  array<string, mixed>  $contents
     */
    public function __construct(
        public int $tenantId,
        public int $sequence,
        public int $versionId,
        public string $versionableType,
        public int $versionableId,
        public array $contents,
    ) {}
}
