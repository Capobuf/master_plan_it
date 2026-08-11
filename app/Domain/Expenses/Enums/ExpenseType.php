<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseType: string
{
    case Estimate = 'estimate';
    case Quote = 'quote';
    case Actual = 'actual';
}
