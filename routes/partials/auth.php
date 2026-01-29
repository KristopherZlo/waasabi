<?php

use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ProfileBadgeController;
use App\Http\Controllers\ProfileFollowController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReadLaterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Services\AutoModerationService;
use App\Services\BadgePayloadService;
use App\Services\BadgeCatalogService;
use App\Services\ContentModerationService;
use App\Services\FeedService;
use App\Services\ImageUploadService;
use App\Services\VisibilityService;
use App\Services\MakerPromotionService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\TopbarPromoService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Route::get('/locale/{locale}', function (string $locale, Request $request) {
    if (!in_array($locale, ['en', 'fi'], true)) {
        abort(400);
    }

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('locale');

Route::get('/login', function () {
    return view('auth.login', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('login');

Route::get('/register', function () {
    return view('auth.register', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('register');

Route::get('/verify-email', function () {
    return view('auth.verify-email', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->middleware('auth')->name('verification.notice');

Route::post('/login', function (Request $request) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', null, ['context' => 'login']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }
    if (captchaEnabled('login') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', null, ['context' => 'login']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }

    $credentials = $request->validate([
        'email' => ['required', 'string', 'email', 'max:255'],
        'password' => ['required'],
    ]);
    $credentials['email'] = strtolower((string) ($credentials['email'] ?? ''));

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = Auth::user();
        if ($user && safeHasColumn('users', 'is_banned') && $user->is_banned) {
            logAuditEvent($request, 'auth.login_banned', $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors([
                'email' => __('ui.auth.banned'),
            ])->onlyInput('email');
        }
        if ($user) {
            logAuditEvent($request, 'auth.login', $user, [
                'email_hash' => hash('sha256', strtolower((string) $user->email)),
            ]);
        }
        return redirect()->route('feed');
    }

    if (!empty($credentials['email'])) {
        logAuditEvent($request, 'auth.login_failed', null, [
            'email_hash' => hash('sha256', strtolower((string) $credentials['email'])),
        ]);
    }
    return back()->withErrors([
        'email' => 'Invalid email or password.',
    ])->onlyInput('email');
})->middleware('throttle:login')->name('login.store');

Route::post('/register', function (Request $request) use ($generateUserSlug) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', null, ['context' => 'register']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }
    if (captchaEnabled('register') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', null, ['context' => 'register']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()],
        'accept_legal' => ['accepted'],
    ]);

    $role = User::count() === 0 ? 'admin' : 'user';
    $slug = $generateUserSlug($data['name']);

    $user = User::create([
        'name' => $data['name'],
        'slug' => $slug,
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => $role,
    ]);

    event(new Registered($user));
    Auth::login($user);
    $request->session()->regenerate();

    logAuditEvent($request, 'auth.register', $user, [
        'email_hash' => hash('sha256', strtolower((string) $user->email)),
    ]);

    return redirect()->route('verification.notice');
})->middleware('throttle:register')->name('register.store');

Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    $user = $request->user();
    if ($user) {
        logAuditEvent($request, 'auth.verify', $user);
    }
    return redirect()->route('feed')->with('toast', __('ui.auth.verify_success'));
})->middleware(['auth', 'signed', 'throttle:verification'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', $request->user(), ['context' => 'verification']);
        return back()->with('toast', __('ui.auth.captcha_failed'));
    }
    if (captchaEnabled('verification') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', $request->user(), ['context' => 'verification']);
        return back()->with('toast', __('ui.auth.captcha_failed'));
    }
    $user = $request->user();
    if ($user && $user->hasVerifiedEmail()) {
        return back()->with('toast', __('ui.auth.verify_already'));
    }
    $user?->sendEmailVerificationNotification();
    if ($user) {
        logAuditEvent($request, 'auth.verify_resend', $user);
    }
    return back()->with('toast', __('ui.auth.verify_sent'));
})->middleware(['auth', 'throttle:verification'])->name('verification.send');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('feed');
})->name('logout');

