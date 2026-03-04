<?php

namespace Laragear\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\DateFactory;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable as TwoFactor;
use function array_pad;
use function explode;
use function is_string;

class ThrottleWithTwoFactor
{
    /**
     * The default key to flag the user has being actively throttled.
     *
     * @const string
     */
    public const THROTTLED_KEY = 'throttled';

    /**
     * The default key to check when the rate limiter should be bypassed.
     *
     * @const string
     */
    public const EXPIRES_AT_KEY = 'expires_at';

    /**
     * The callback used to generate the rate limiter key.
     *
     * @var \Closure(\Illuminate\Http\Request): string
     */
    public static Closure $key;

    /**
     * A callback that should be used to determine if the request should be throttled or not.
     *
     * @var \Closure(\Illuminate\Http\Request): bool
     */
    public static Closure $shouldThrottle;

    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected Repository $config,
        protected ResponseFactory $factory,
        protected RateLimiter $limiter,
        protected DateFactory $date,
    ) {
        // ...
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        $sessionKey = $this->config->get('two-factor.throttle.session_key');

        if ($user instanceof TwoFactor && $user->hasTwoFactorEnabled() && $this->isThrottling($request, $sessionKey)) {
            $key = $this->getRateLimiterKey($request);

            [$tries, $decay] = $this->parseConfiguredAttempts();

            if ($this->limiter->tooManyAttempts($key, $tries)) {
                return $this->throttleUser($request, $sessionKey);
            }

            $this->limiter->hit($key, $decay);
        }

        return $next($request);
    }

    /**
     * Checks if the session has as a bypass timestamp, and it's set in the future.
     */
    protected function isThrottling(Request $request, string $sessionKey): bool
    {
        $shouldThrottle = (static::$shouldThrottle)($request);

        // If the expiration timestamp exists, check is not set in the future (has expired).
        if ($shouldThrottle && $expiresAt = $request->session()->get($sessionKey.self::EXPIRES_AT_KEY, 0)) {
            return $this->date->createFromTimestamp($expiresAt)->isPast();
        }

        return true;
    }

    /**
     * Parses the attempts configuration.
     *
     * @return array{0: int, 1: int|null}
     */
    protected function parseConfiguredAttempts(): array
    {
        $values = $this->config->get('two-factor.throttle.enabled');

        $values = is_string($values)
            ? explode(',', $values, 2)
            : $this->config->get('two-factor.throttle.tries');

        return array_pad($values, 2, null);
    }

    /**
     * Throttles the user, setting a flag in the session and redirecting him to an especial route.
     */
    protected function throttleUser(Request $request, string $sessionKey): RedirectResponse
    {
        [
            'two-factor.throttle.route' => $route,
            'two-factor.throttle.lifetime' => $expiresAt,
        ] = $this->config->get([
            'two-factor.throttle.route',
            'two-factor.throttle.lifetime',
        ]);

        $request->session()->put($sessionKey.static::EXPIRES_AT_KEY, $this->date->now()->addMinutes($expiresAt));

        return $this->factory->redirectToRoute($route);
    }

    /**
     * Clear the attempts for the given user.
     */
    public function clearAttempts(Request $request): void
    {
        $this->limiter->clear($this->getRateLimiterKey($request));
    }

    /**
     * Returns the key to use with the Rate Limiter.
     */
    protected function getRateLimiterKey(Request $request): string
    {
        return $this->config->get('two-factor.throttle.prefix', 'totp.throttle') . (static::$key)($request);
    }
}
