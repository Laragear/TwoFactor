<?php

namespace Laragear\TwoFactor\Events;

use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorRecoveryCodesGenerated
{
    /**
     * Create a new event instance.
     */
    public function __construct(public TwoFactorAuthenticatable $user)
    {
        //
    }
}
