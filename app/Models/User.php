<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Tenancy\Enums\TenantState;
use App\Support\Authorization\PlatformAdministrator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'is_active', 'lock_version'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin' || ! $this->exists) {
            return false;
        }

        $keyName = $this->getKeyName();
        $currentKey = $this->getKey();
        $originalKey = $this->getRawOriginal($keyName);

        if (
            (! is_int($currentKey) && ! is_string($currentKey))
            || (! is_int($originalKey) && ! is_string($originalKey))
            || $currentKey !== $originalKey
        ) {
            return false;
        }

        $persistedUser = self::query()
            ->with('tenant')
            ->find($originalKey);

        if ($persistedUser === null || ! $persistedUser->is_active) {
            return false;
        }

        if ($persistedUser->tenant_id === null) {
            return app(PlatformAdministrator::class)->hasProtectedRole($persistedUser);
        }

        return $persistedUser->tenant?->state === TenantState::Active;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_user_id');
    }

    /**
     * @return HasMany<PlatformSetting, $this>
     */
    public function updatedPlatformSettings(): HasMany
    {
        return $this->hasMany(PlatformSetting::class, 'updated_by_user_id');
    }

    /**
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }
}
