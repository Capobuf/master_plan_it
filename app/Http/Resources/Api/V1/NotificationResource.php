<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    private const SENSITIVE = ['password', 'token', 'secret', 'payload', 'credentials', 'authorization'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->safeData(is_array($this->resource->data) ? $this->resource->data : []);

        return [
            'id' => (string) $this->resource->getKey(), 'type' => (string) $this->resource->type,
            'data' => $data, 'delivery_status' => $data['delivery_status'] ?? null,
            'delivery_error' => $data['delivery_error'] ?? null,
            'read_at' => $this->resource->read_at?->toISOString(), 'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /** @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function safeData(array $data): array
    {
        $safe = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && collect(self::SENSITIVE)->contains(
                fn (string $part): bool => str_contains(strtolower($key), $part),
            )) {
                continue;
            }

            $safe[$key] = is_array($value)
                ? $this->safeData($value)
                : (is_scalar($value) || $value === null ? $value : null);
        }

        return $safe;
    }
}
