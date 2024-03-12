<?php

namespace Tests\Rules;

use Illuminate\Support\Facades\Date;
use Tests\CreatesTwoFactorUser;
use Tests\Stubs\UserStub;
use Tests\TestCase;

use function validator;

class TotpRuleTest extends TestCase
{
    use CreatesTwoFactorUser;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);

        parent::setUp();
    }

    protected function defineWebRoutes($router)
    {
        $router->get('intended', static function (): string {
            return 'ok';
        })->name('intended')->middleware('auth', '2fa.confirm');
    }

    public function test_validation_fails_if_guest(): void
    {
        $fails = $this->app->make('validator')->make([
            'code' => '123456',
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertTrue($fails);
    }

    public function test_validation_fails_if_user_is_not_2fa(): void
    {
        $this->actingAs(UserStub::create([
            'name' => 'test',
            'email' => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]));

        $fails = $this->app->make('validator')->make([
            'code' => '123456',
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_not_enabled(): void
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $fails = $this->app->make('validator')->make([
            'code' => '123456',
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_code_is_invalid(): void
    {
        $this->actingAs($this->user);

        $fails = $this->app->make('validator')->make([
            'code' => '123456',
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_code_is_expired_over_window(): void
    {
        $this->travelTo($now = Date::create(2020, 04, 01, 16, 30));

        $this->actingAs($this->user);

        $fails = $this->app->make('validator')->make([
            'code' => $this->user->makeTwoFactorCode($now, -2),
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertTrue($fails);
    }

    public function test_validator_succeeds_if_code_valid(): void
    {
        $this->travelTo($now = Date::create(2020, 04, 01, 16, 30));

        $this->actingAs($this->user);

        $fails = validator([
            'code' => $this->user->makeTwoFactorCode($now),
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertFalse($fails);
    }

    public function test_validator_succeeds_if_code_is_recovery(): void
    {
        $this->actingAs($this->user);

        $fails = $this->app->make('validator')->make([
            'code' => $this->user->generateRecoveryCodes()->first()['code'],
        ], [
            'code' => 'totp',
        ])->fails();

        static::assertFalse($fails);
    }

    public function test_validator_fails_if_code_is_recovery_and_excludes_recovery_codes(): void
    {
        $validator = $this->app->make('validator')->make([
            'code' => $this->user->generateRecoveryCodes()->first()['code'],
        ], [
            'code' => 'totp:code',
        ]);

        static::assertSame('The Code is invalid or has expired.', $validator->errors()->first('code'));
    }

    public function test_validator_rule_uses_translation(): void
    {
        $validator = $this->app->make('validator')->make([
            'code' => 'invalid',
        ], [
            'code' => 'totp',
        ]);

        static::assertSame('The Code is invalid or has expired.', $validator->errors()->first('code'));
    }
}
