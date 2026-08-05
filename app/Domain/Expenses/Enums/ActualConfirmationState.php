<?php

namespace App\Domain\Expenses\Enums;

enum ActualConfirmationState: string
{
    case ToConfirm = 'to_confirm';
    case Confirmed = 'confirmed';
}
