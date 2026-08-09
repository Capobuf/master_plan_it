<?php

namespace App\Domain\Revisions\Data;

enum RevisionMutation: string
{
    case Upsert = 'upsert';
    case Delete = 'delete';
}
