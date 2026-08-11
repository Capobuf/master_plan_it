<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseKind: string
{
    case Ordinary = 'ordinary';
    case Plafond = 'plafond';
}
