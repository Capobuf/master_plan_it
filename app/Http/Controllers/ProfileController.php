<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ChangeOwnPassword;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function updatePassword(Request $request, ChangeOwnPassword $changeOwnPassword): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $actor = $request->user();
        $context = $request->attributes->get(TenantContext::class);

        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $changeOwnPassword->execute(
            $actor,
            $context instanceof TenantContext ? $context : null,
            $validated['current_password'],
            $validated['password'],
            CorrelationId::resolveFor($request)->value(),
        );

        return redirect()->route('profile.edit')->with('success', 'Password updated.');
    }
}
