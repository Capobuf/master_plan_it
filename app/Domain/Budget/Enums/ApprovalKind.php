<?php

namespace App\Domain\Budget\Enums;

enum ApprovalKind: string
{
    case Initial = 'initial';
    case Variation = 'variation';
}
