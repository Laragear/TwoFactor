<?php

namespace Tests;

use Laragear\TwoFactor\Models\TwoFactorAuthentication;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;

trait CreatesTwoFactorUser
{
    protected UserTwoFactorStub $user;

    protected function createTwoFactorUser(): void
    {
        $this->user = UserTwoFactorStub::create([
            'name' => 'foo',
            'email' => 'foo@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $this->user->twoFactorAuth()->save(
            TwoFactorAuthentication::factory()->make()
        );
    }
}
