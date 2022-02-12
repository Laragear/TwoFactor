<?php

namespace Laragear\TwoFactor\Events;

use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorRecoveryCodesGenerated
{
    /**
     * Create a new event instance.
     *
     * @param  \Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable  $user
     * @return void
     */
    public function __construct(public TwoFactorAuthenticatable $user)
    {
        //
    }
}
