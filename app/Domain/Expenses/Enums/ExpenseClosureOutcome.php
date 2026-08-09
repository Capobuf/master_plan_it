<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseClosureOutcome: string
{
    case NotIncurred = 'not_incurred';
    case Cancelled = 'cancelled';
    case Moved = 'moved';
}
