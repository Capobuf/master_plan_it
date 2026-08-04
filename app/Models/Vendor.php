<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable([
    'tenant_id',
    'name',
    'vat_number',
    'email',
    'phone',
    'address',
    'active',
    'lock_version',
])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, SoftDeletes, Versionable;

    /** @var list<string> */
    protected array $versionable = [
        'name',
        'vat_number',
        'email',
        'phone',
        'address',
        'active',
    ];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
