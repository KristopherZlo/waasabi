<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || empty($user->is_banned)) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $message = __('ui.js.user_banned');
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'banned' => true], 403);
        }

        return redirect()->back()->with('toast', $message);
    }
}
