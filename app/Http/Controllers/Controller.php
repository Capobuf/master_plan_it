<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $actor;
    }

    protected function tenantContext(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return $context;
    }

    protected function correlationId(Request $request): string
    {
        return CorrelationId::resolveFor($request)->value();
    }

    protected function nullableString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
