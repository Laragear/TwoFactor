<?php

namespace Laragear\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable as TwoFactor;

use function response;
use function trans;

class RequireTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $route
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $route = '2fa.notice'): mixed
    {
        $user = $request->user();

        if (! $user instanceof TwoFactor || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => trans('two-factor::messages.enable')], 403)
            : response()->redirectToRoute($route);
    }
}
