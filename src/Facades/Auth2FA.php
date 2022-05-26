<?php

namespace Laragear\TwoFactor\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\TwoFactor\TwoFactorLoginHelper;

/**
 * @method bool attempt(array $credentials = [], mixed $remember = false)
 * @method \Laragear\TwoFactor\TwoFactorLoginHelper view(string $view)
 * @method \Laragear\TwoFactor\TwoFactorLoginHelper message(string $message)
 * @method \Laragear\TwoFactor\TwoFactorLoginHelper input(string $input)
 * @method \Laragear\TwoFactor\TwoFactorLoginHelper sessionKey(string $sessionKey)
 * @method \Laragear\TwoFactor\TwoFactorLoginHelper guard(string $guard)
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
