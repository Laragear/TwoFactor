<?php

namespace Laragear\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable as TwoFactor;

use function config;
use function now;
use function response;
use function url;

class ConfirmTwoFactorCode
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $route = '2fa.confirm',
        string|bool $force = 'false'
    ): mixed {
        $user = $request->user();

        if (
            ! $user instanceof TwoFactor ||
            ! $user->hasTwoFactorEnabled() ||
            $this->recentlyConfirmed($request, $route, $force)
        ) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => trans('two-factor::messages.required')], 403)
            : response()->redirectGuest(url()->route($route));
    }

    /**
     * Determine if the confirmation timeout has expired.
     */
    protected function recentlyConfirmed(Request $request, string $route, string $force): bool
    {
        // If the developer is forcing this middleware to always run regardless of the
        // confirmation "reminder", then skip that logic and always return "false".
        // Otherwise, find the session key and check it has not expired already.
        if (
            in_array(strtolower($route), ['true', 'force'], true) ||
            in_array(strtolower($force), ['true', 'force'], true)
        ) {
            return false;
        }

        $key = config('two-factor.confirm.key');

        return $request->session()->get("$key.confirm.expires_at") >= now()->getTimestamp();
    }
}
