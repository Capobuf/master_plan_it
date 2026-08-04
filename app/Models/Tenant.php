<?php

namespace App\Models;

use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Builders\TenantBuilder;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable([
    'name',
    'code',
    'state',
    'currency_code',
    'language_code',
    'timezone',
    'default_vat_rate',
    'budget_basis',
    'attachment_quota_bytes',
    'deletion_reason_required',
    'company_name',
    'address',
    'contact_name',
    'contact_email',
    'contact_phone',
    'report_logo_path',
    'created_by_user_id',
    'state_changed_by_user_id',
    'state_changed_at',
    'lock_version',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => TenantState::class,
            'default_vat_rate' => 'decimal:6',
            'budget_basis' => BudgetBasis::class,
            'attachment_quota_bytes' => 'string',
            'deletion_reason_required' => 'boolean',
            'state_changed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Tenants cannot be permanently deleted.');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function stateChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_changed_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
