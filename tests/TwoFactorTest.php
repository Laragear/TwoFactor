<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Laragear\TwoFactor\Exceptions\InvalidCodeException;
use Laragear\TwoFactor\TwoFactor;
use Mockery;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;

use function now;

class TwoFactorTest extends TestCase
{
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;
    use WithFaker;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        $this->afterApplicationCreated(function (): void {
            app('config')->set('auth.providers.users.model', UserTwoFactorStub::class);
            $this->travelTo(today());
        });

        parent::setUp();
    }

    public function test_authenticates_with_when(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
    }

    public function test_authenticates_with_when_with_no_exceptions(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCodeOrFails()));
    }

    public function test_authenticates_with_when_with_recovery_code(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->getRecoveryCodes()->first()['code'],
        ]));

        $this->travelTo($now = now());

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
        static::assertEquals($now->toIso8601ZuluString('microsecond'), $this->user->fresh()->getRecoveryCodes()->first()['used_at']);
    }

    public function test_authenticates_with_when_with_recovery_code_with_no_exceptions(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->getRecoveryCodes()->first()['code'],
        ]));

        $this->travelTo($now = now());

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCodeOrFails()));
        static::assertEquals($now->toIso8601ZuluString('microsecond'), $this->user->fresh()->getRecoveryCodes()->first()['used_at']);
    }

    public function test_authenticates_with_different_input_name(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            'foo_bar' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode('foo_bar')));
    }

    public function test_doesnt_authenticates_with_invalid_code(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid',
        ]));

        static::assertFalse(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
    }

    public function test_non_two_factor_user_bypasses_checks(): void
    {
        $this->app->make('config')->set('auth.providers.users.model', UserStub::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST'));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
    }

    public function test_user_without_2fa_enabled_bypasses_check(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->user->disableTwoFactorAuth();

        $this->instance('request', Request::create('test', 'POST'));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
    }

    public function test_validation_exception_when_code_invalid(): void
    {
        $this->expectException(InvalidCodeException::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid',
        ]));

        try {
            Auth::attemptWhen($credentials, TwoFactor::hasCodeOrFails());
        } catch (ValidationException $exception) {
            static::assertSame(['2fa_code' => ['The Code is invalid or has expired.']], $exception->errors());
            throw $exception;
        }
    }

    public function test_validation_exception_when_code_empty(): void
    {
        $this->expectException(InvalidCodeException::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => '',
        ]));

        try {
            Auth::attemptWhen($credentials, TwoFactor::hasCodeOrFails());
        } catch (ValidationException $exception) {
            static::assertSame(['2fa_code' => ['The Code is invalid or has expired.']], $exception->errors());
            throw $exception;
        }
    }

    public function test_validation_exception_with_message_when_code_invalid(): void
    {
        $this->expectException(InvalidCodeException::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid',
        ]));

        try {
            Auth::attemptWhen($credentials, TwoFactor::hasCodeOrFails(message: 'foo'));
        } catch (ValidationException $exception) {
            static::assertSame(['2fa_code' => ['foo']], $exception->errors());
            throw $exception;
        }
    }

    public function test_saves_safe_device(): void
    {
        $this->app->make('config')->set('two-factor.safe_devices.enabled', true);

        Cookie::partialMock()->shouldReceive('queue')
            ->with('_2fa_remember', Mockery::type('string'), 14 * 1440)
            ->once();

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
            'safe_device' => 'on',
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));
        static::assertCount(1, $this->user->fresh()->safeDevices());

        $this->assertAuthenticatedAs($this->user);

        static::assertFalse($this->app->make('auth')->user()->wasTwoFactorBypassedBySafeDevice());
    }

    public function test_doesnt_adds_safe_device_when_input_not_filled(): void
    {
        $this->app->make('config')->set('two-factor.safe_devices.enabled', true);

        Cookie::partialMock()->shouldNotReceive('queue');

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));

        static::assertEmpty($this->user->fresh()->safeDevices());
    }

    public function test_bypasses_totp_if_safe_devices(): void
    {
        $this->app->make('config')->set('two-factor.safe_devices.enabled', true);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret',
        ];

        $this->instance('request', $request = Request::create('test', 'POST'));

        $token = $this->user->addSafeDevice($request);

        $request->cookies->set('_2fa_remember', $token);

        static::assertTrue(Auth::attemptWhen($credentials, TwoFactor::hasCode()));

        $this->assertAuthenticatedAs($this->user);

        static::assertTrue($this->app->make('auth')->user()->wasTwoFactorBypassedBySafeDevice());
    }
}
