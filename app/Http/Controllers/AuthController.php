<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserPayloadService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['en', 'fi'], true), 400);
        $request->session()->put('locale', $locale);

        return back();
    }

    public function loginForm(UserPayloadService $payloads): Response
    {
        return Inertia::render('Auth', ['mode' => 'login']);
    }

    public function registerForm(UserPayloadService $payloads): Response
    {
        return Inertia::render('Auth', ['mode' => 'register']);
    }

    public function verificationForm(UserPayloadService $payloads): Response
    {
        return Inertia::render('Auth', ['mode' => 'verify']);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        if ($this->botCheckFailed($request, 'login')) {
            return back()->withErrors(['email' => __('ui.auth.captcha_failed')])->onlyInput('email');
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required'],
        ]);
        if (! Auth::attempt($credentials)) {
            logAuditEvent($request, 'auth.login_failed', null, [
                'email_hash' => hash('sha256', $credentials['email']),
            ]);

            return back()->withErrors(['email' => __('ui.auth.invalid_credentials')])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        if ($user?->is_banned) {
            logAuditEvent($request, 'auth.login_banned', $user);
            $this->endSession($request);

            return back()->withErrors(['email' => __('ui.auth.banned')])->onlyInput('email');
        }

        if ($user) {
            logAuditEvent($request, 'auth.login', $user, [
                'email_hash' => hash('sha256', strtolower($user->email)),
            ]);
        }

        return redirect()->route('feed');
    }

    public function register(Request $request, UserSlugService $slugs): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        if ($this->botCheckFailed($request, 'register')) {
            return back()->withErrors(['email' => __('ui.auth.captcha_failed')])->onlyInput('email');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()],
            'accept_legal' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'slug' => $slugs->generate($data['name']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'legal_version' => config('hub.legal_version'),
            'legal_accepted_at' => now(),
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        logAuditEvent($request, 'auth.register', $user, [
            'email_hash' => hash('sha256', strtolower($user->email)),
        ]);

        return redirect()->route('verification.notice');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();
        if ($request->user()) {
            logAuditEvent($request, 'auth.verify', $request->user());
        }

        return redirect()->route('feed')->with('toast', __('ui.auth.verify_success'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if ($this->botCheckFailed($request, 'verification')) {
            return back()->with('toast', __('ui.auth.captcha_failed'));
        }

        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return back()->with('toast', __('ui.auth.verify_already'));
        }

        $user->sendEmailVerificationNotification();
        logAuditEvent($request, 'auth.verify_resend', $user);

        return back()->with('toast', __('ui.auth.verify_sent'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->endSession($request);

        return redirect()->route('feed');
    }

    private function botCheckFailed(Request $request, string $context): bool
    {
        if (honeypotTripped($request)) {
            logAuditEvent($request, 'auth.honeypot', $request->user(), compact('context'));

            return true;
        }

        if (captchaEnabled($context) && ! verifyCaptcha($request)) {
            logAuditEvent($request, 'auth.captcha_failed', $request->user(), compact('context'));

            return true;
        }

        return false;
    }

    private function endSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
