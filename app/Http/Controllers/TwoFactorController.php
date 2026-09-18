<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function enable(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        $codes = $twoFactor->recoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $twoFactor->secret(),
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();
        logAuditEvent($request, 'auth.two_factor_started', $user);

        return redirect(route('profile.settings').'#security')->with('toast', __('studio.two_factor_scan'));
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $request->user();
        abort_unless($user->two_factor_secret && ! $user->two_factor_confirmed_at, 409);
        if (! $twoFactor->verify($user, $data['code'])) {
            return back()->withErrors(['code' => __('studio.two_factor_invalid')]);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->regenerate();
        logAuditEvent($request, 'auth.two_factor_enabled', $user);

        return redirect(route('profile.settings').'#security')
            ->with('two_factor_recovery_codes', $user->two_factor_recovery_codes)
            ->with('toast', __('studio.two_factor_enabled'));
    }

    public function recoveryCodes(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        abort_unless($user->two_factor_confirmed_at, 409);
        $codes = $twoFactor->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        logAuditEvent($request, 'auth.two_factor_recovery_codes', $user);

        return redirect(route('profile.settings').'#security')
            ->with('two_factor_recovery_codes', $codes)
            ->with('toast', __('studio.two_factor_recovery_regenerated'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->regenerate();
        logAuditEvent($request, 'auth.two_factor_disabled', $user);

        return redirect(route('profile.settings').'#security')->with('toast', __('studio.two_factor_disabled'));
    }
}
