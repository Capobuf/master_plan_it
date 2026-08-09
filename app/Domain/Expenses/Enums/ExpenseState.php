<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseState: string
{
    case Open = 'open';
    case Closed = 'closed';
}
