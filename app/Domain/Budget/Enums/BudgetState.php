<?php

namespace App\Domain\Budget\Enums;

enum BudgetState: string
{
    case Preparation = 'preparation';
    case Approved = 'approved';
    case Closed = 'closed';
}
