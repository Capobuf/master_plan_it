<?php

namespace App\Domain\Tenancy\Enums;

enum BudgetBasis: string
{
    case Net = 'net';
    case Gross = 'gross';
}
