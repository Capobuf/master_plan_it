<?php

namespace App\Domain\Budget\Enums;

enum ApprovalContributorKind: string
{
    case OrdinaryCurrentPlanning = 'ordinary_current_planning';
    case PlafondAllocation = 'plafond_allocation';
}
