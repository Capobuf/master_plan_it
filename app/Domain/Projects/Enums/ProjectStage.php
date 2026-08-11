<?php

namespace App\Domain\Projects\Enums;

enum ProjectStage: string
{
    case Idea = 'idea';
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Deferred = 'deferred';
    case Rejected = 'rejected';
}
