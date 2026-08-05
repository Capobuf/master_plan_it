<?php

namespace App\Domain\Revisions\Data;

enum RevisionOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Deactivate = 'deactivate';
    case Reactivate = 'reactivate';
    case Restore = 'restore';
    case Delete = 'delete';
}
