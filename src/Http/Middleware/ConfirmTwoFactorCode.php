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
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $route
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $route = '2fa.confirm'): mixed
    {
        $user = $request->user();

        if (! $user instanceof TwoFactor || ! $user->hasTwoFactorEnabled() || $this->recentlyConfirmed($request)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => trans('two-factor::messages.required')], 403)
            : response()->redirectGuest(url()->route($route));
    }

    /**
     * Determine if the confirmation timeout has expired.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function recentlyConfirmed(Request $request): bool
    {
        $key = config('two-factor.confirm.key');

        return $request->session()->get("$key.confirm.expires_at") >= now()->getTimestamp();
    }
}
