<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Platform\Actions\UpdateAuditRetention;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformSettingResource;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformSettingsController extends Controller
{
    public function show(): PlatformSettingResource
    {
        return PlatformSettingResource::make(PlatformSetting::query()->findOrFail(1));
    }

    public function updateRetention(Request $request, UpdateAuditRetention $action): JsonResponse
    {
        $validated = $request->validate([
            'audit_retention_months' => ['required', 'integer', 'between:1,120'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'confirmation' => ['nullable', 'string'],
        ]);
        [$setting, $deleted] = $action->execute($this->actor($request), (int) $validated['audit_retention_months'],
            (int) $validated['lock_version'], $validated['confirmation'] ?? null, $this->correlationId($request));

        return response()->json(['data' => [
            ...(new PlatformSettingResource($setting))->resolve($request),
            'deleted_audit_events' => $deleted,
        ]]);
    }
}
