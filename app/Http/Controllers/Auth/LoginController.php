<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class LoginController extends Controller
{
    public function __construct(private readonly PlatformAdministrator $platformAdministrator) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $throttleKey = $this->throttleKey($request, $email);

        $this->ensureIsNotRateLimited($throttleKey);

        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Ordinary login requires a session authentication guard.');
        }

        if (! $guard->attempt([
            'email' => $email,
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $actor = $guard->user();

        if (! $actor instanceof User || ! $this->mayUseApplication($actor)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget(EnterTenantContext::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function mayUseApplication(User $actor): bool
    {
        if ($actor->tenant_id === null) {
            return $this->platformAdministrator->hasProtectedRole($actor);
        }

        return $actor->tenant()->where('state', TenantState::Active->value)->exists();
    }

    private function ensureIsNotRateLimited(string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return Str::transliterate($email).'|'.$request->ip();
    }
}
