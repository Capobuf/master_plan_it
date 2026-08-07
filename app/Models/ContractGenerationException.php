<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $contract_id
 * @property string $source_key
 * @property Carbon $suppressed_at
 * @property string|null $reason
 */
#[Fillable(['tenant_id', 'contract_id', 'source_key', 'suppressed_by_user_id', 'suppressed_at', 'reason'])]
class ContractGenerationException extends Model
{
    protected function casts(): array
    {
        return ['suppressed_at' => 'datetime'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
