<?php

namespace Laragear\TwoFactor\Events;

use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorEnabled
{
    /**
     * Create a new event instance.
     */
    public function __construct(public TwoFactorAuthenticatable $user)
    {
        //
    }
}
