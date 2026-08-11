<?php

namespace App\Domain\Expenses\Enums;

enum Distribution: string
{
    case All = 'all';
    case Start = 'start';
    case End = 'end';
}
