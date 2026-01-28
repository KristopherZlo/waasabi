<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAge
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?int $minutes = null): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $minAge = $minutes !== null
            ? max(0, (int) $minutes)
            : max(0, (int) config('waasabi.security.min_account_age_minutes', 0));

        if ($minAge <= 0) {
            return $next($request);
        }

        $createdAt = $user->created_at;
        if (!$createdAt) {
            return $next($request);
        }

        if ($createdAt->diffInMinutes(now()) >= $minAge) {
            return $next($request);
        }

        $message = __('ui.auth.account_too_new', ['minutes' => $minAge]);
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 429);
        }

        return redirect()->back()->with('toast', $message);
    }
}
