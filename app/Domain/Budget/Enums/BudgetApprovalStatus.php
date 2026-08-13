<?php

namespace App\Domain\Budget\Enums;

enum BudgetApprovalStatus: string
{
    case Active = 'active';
    case Annulled = 'annulled';
}
