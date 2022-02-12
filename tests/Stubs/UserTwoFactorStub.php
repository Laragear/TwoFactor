<?php

namespace Tests\Stubs;

use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property string $email
 */
class UserTwoFactorStub extends UserStub implements TwoFactorAuthenticatable
{
    use TwoFactorAuthentication;
}
