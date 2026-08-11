<?php

namespace App\Domain\Revisions\Data;

enum RevisionActorKind: string
{
    case Human = 'human';
    case System = 'system';

    public function label(): string
    {
        return $this === self::System ? 'Sistema' : 'Utente';
    }
}
