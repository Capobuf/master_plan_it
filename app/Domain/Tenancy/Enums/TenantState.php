<?php

namespace App\Domain\Tenancy\Enums;

enum TenantState: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
