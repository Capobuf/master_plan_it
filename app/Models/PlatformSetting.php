<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'audit_retention_months', 'lock_version', 'updated_by_user_id'])]
class PlatformSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audit_retention_months' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
