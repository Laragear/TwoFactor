<?php

namespace Laragear\TwoFactor\Events;

use Illuminate\Queue\SerializesModels;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorEnabled
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public TwoFactorAuthenticatable $user)
    {
        //
    }
}
