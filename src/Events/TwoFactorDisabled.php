<?php

namespace Laragear\TwoFactor\Events;

use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorDisabled
{
    /**
     * Create a new event instance.
     */
    public function __construct(public TwoFactorAuthenticatable $user)
    {
        //
    }
}
