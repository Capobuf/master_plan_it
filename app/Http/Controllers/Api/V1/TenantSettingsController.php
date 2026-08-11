<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Actions\UpdateTenantSettings;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantSettingsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TenantSettingsController extends Controller
{
    public function show(Request $request): TenantSettingsResource
    {
        return TenantSettingsResource::make($this->tenantContext($request)->tenant->fresh());
    }

    public function update(Request $request, UpdateTenantSettings $action): TenantSettingsResource
    {
        $allowed = [
            'name',
            'timezone',
            'default_vat_rate',
            'budget_basis',
            'deletion_reason_required',
            'lock_version',
        ];
        $unexpected = array_diff(array_keys($request->all()), $allowed);

        if ($unexpected !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unexpected, 'This field is not allowed for Tenant settings.'),
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_vat_rate' => ['required', 'string'],
            'budget_basis' => ['required', 'string'],
            'deletion_reason_required' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $lockVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        return TenantSettingsResource::make($action->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $validated,
            $lockVersion,
            $this->correlationId($request),
        ));
    }
}
