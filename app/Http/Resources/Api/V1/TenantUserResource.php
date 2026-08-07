<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Spatie\Permission\Models\Role;

/** @property-read User $resource */
final class TenantUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $roles = $this->resource->relationLoaded('roles')
            ? $this->resource->roles
                ->map(static function (Model $role): array {
                    if (! $role instanceof Role) {
                        throw new LogicException('Unexpected role model in tenant user resource.');
                    }

                    return [
                        'id' => (int) $role->getKey(),
                        'name' => (string) $role->name,
                    ];
                })
                ->values()
                ->all()
            : [];

        return [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'email' => (string) $this->resource->email,
            'active' => (bool) $this->resource->is_active,
            'lock_version' => (int) $this->resource->lock_version,
            'roles' => $roles,
            'role_ids' => array_values(array_map(
                static fn (array $role): int => $role['id'],
                $roles,
            )),
        ];
    }
}
