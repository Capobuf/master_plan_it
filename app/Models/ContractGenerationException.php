<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'contract_id', 'source_key', 'suppressed_by_user_id', 'suppressed_at', 'reason'])]
class ContractGenerationException extends Model
{
    protected function casts(): array { return ['suppressed_at' => 'datetime']; }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
