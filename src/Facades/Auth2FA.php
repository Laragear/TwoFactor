<?php

namespace Laragear\TwoFactor\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\TwoFactor\TwoFactorLoginHelper;

/**
 * @method static bool attempt(array $credentials = [], mixed $remember = false)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper view(string $view)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper message(string $message)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper input(string $input)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper sessionKey(string $sessionKey)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper guard(string $guard)
 * @method static \Laragear\TwoFactor\TwoFactorLoginHelper redirect(string $route)
 *
 * @see \Laragear\TwoFactor\TwoFactorLoginHelper
 */
class Auth2FA extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return TwoFactorLoginHelper::class;
    }
}
