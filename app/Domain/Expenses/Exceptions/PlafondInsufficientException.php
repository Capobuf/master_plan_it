<?php

namespace App\Domain\Expenses\Exceptions;

use App\Domain\Expenses\Data\PlafondInsufficiency;
use DomainException;

final class PlafondInsufficientException extends DomainException
{
    public function __construct(public readonly PlafondInsufficiency $insufficiency)
    {
        parent::__construct('PLAFOND_INSUFFICIENT');
    }
}
