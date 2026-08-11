<?php

namespace App\Domain\Expenses\Data;

/**
 * Immutable snapshot of one aggregate revision: the header business values
 * and the ordered row business values, free of technical/tenant/lock flags.
 */
final readonly class ExpenseRevisionSnapshot
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public array $header,
        public array $rows,
    ) {}
}
