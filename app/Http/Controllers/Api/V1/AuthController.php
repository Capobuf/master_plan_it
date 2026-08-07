<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IdentityAccess\Actions\ChangeOwnPassword;
use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AuthController extends Controller
{
    public function login(Request $request): UserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim((string) $credentials['email']));
        $throttleKey = Str::transliterate($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
            ]);
        }

        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Session authentication guard is required for first-party API login.');
        }

        if (! $guard->attempt([
            'email' => $email,
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $actor = $guard->user();

        if (! $actor instanceof User || ! $this->mayUseApplication($actor)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget(EnterTenantContext::SESSION_KEY);
        $request->session()->regenerate();

        return UserResource::make($actor);
    }

    public function logout(Request $request): Response
    {
        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Session authentication guard is required for first-party API logout.');
        }

        $guard->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($this->actor($request));
    }

    public function password(Request $request, ChangeOwnPassword $changeOwnPassword, ResolveTenantContext $resolver): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $actor = $this->actor($request);
        $context = $resolver->resolveIfPresent($request);

        if ($context instanceof TenantContext) {
            if ($actor->tenant_id !== null && $context->tenant->state === TenantState::Inactive) {
                throw new AuthorizationException('TENANT_INACTIVE');
            }

            $request->attributes->set(TenantContext::class, $context);
        }

        $changeOwnPassword->execute(
            $actor,
            $context,
            (string) $validated['current_password'],
            (string) $validated['password'],
            CorrelationId::resolveFor($request)->value(),
        );

        return response()->noContent();
    }

    private function mayUseApplication(User $actor): bool
    {
        if ($actor->tenant_id === null) {
            return app(PlatformAdministrator::class)->hasProtectedRole($actor);
        }

        return $actor->tenant()->where('state', TenantState::Active->value)->exists();
    }
}
