<?php

namespace App\Domain\Contracts\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
}
