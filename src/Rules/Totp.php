<?php

namespace Laragear\TwoFactor\Rules;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

use function is_string;

/**
 * @internal
 */
class Totp
{
    /**
     * Create a new "totp code" rule instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     */
    public function __construct(protected ?Authenticatable $user = null)
    {
        //
    }

    /**
     * Validate that an attribute is a valid Two-Factor Authentication TOTP code.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    public function validate(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value)
            && $this->user instanceof TwoFactorAuthenticatable
            && $this->user->validateTwoFactorCode($value, Arr::first($parameters) !== 'code');
    }
}
