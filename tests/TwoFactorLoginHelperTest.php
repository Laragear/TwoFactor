<?php

namespace Tests;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Laragear\TwoFactor\Facades\Auth2FA;
use Mockery;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;
use function app;
use function config;
use function today;
use function trans;

class TwoFactorLoginHelperTest extends TestCase
{
    use RefreshDatabase;
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

    public function test_authenticates_non_2fa_user_with_credentials(): void
    {
        app('config')->set('auth.providers.users.model', UserStub::class);

        $this->instance('request', Request::create('test', 'POST'));

        static::assertTrue(Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]));
    }

    public function test_doesnt_authenticates_non_2fa_user_with_failed_credentials(): void
    {
        app('config')->set('auth.providers.users.model', UserStub::class);

        $this->instance('request', Request::create('test', 'POST'));

        static::assertFalse(Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'invalid',
        ]));
    }

    public function test_authenticates_2fa_user_with_credentials_and_2fa_disabled(): void
    {
        $this->user->disableTwoFactorAuth();

        $this->instance('request', Request::create('test', 'POST'));

        static::assertTrue(Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]));
    }

    public function test_authenticates_2fa_user_with_credentials_and_totp_code(): void
    {
        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]));
    }

    public function test_authenticates_2fa_user_with_credentials_and_recovery_code(): void
    {
        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->getRecoveryCodes()->first()['code'],
        ]));

        static::assertTrue(Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]));
    }

    public function test_throws_response_for_2fa_code_if_no_2fa_code(): void
    {
        $this->expectException(HttpResponseException::class);

        $this->instance('request', Request::create('test', 'POST'));

        Auth2FA::attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]);
    }

    public function test_thrown_response_is_form_view(): void
    {
        try {
            Auth2FA::attempt([
                'email' => $this->user->email,
                'password' => 'secret',
            ]);

            static::fail('HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $response = new TestResponse($e->getResponse());

            $response->assertViewIs('two-factor::login')
                ->assertViewHas('input', '2fa_code')
                ->assertViewHas('errors', static function (ViewErrorBag $errors): bool {
                    static::assertTrue($errors->has('2fa_code'));
                    static::assertSame(trans('two-factor::validation.totp_code'), $errors->first('2fa_code'));

                    return true;
                })
                ->assertSessionHas('_2fa_login.credentials.email', function (string $email): bool {
                    static::assertSame($this->user->email, Crypt::decryptString($email));

                    return true;
                })
                ->assertSessionHas('_2fa_login.credentials.password', static function (string $password): bool {
                    static::assertSame('secret', Crypt::decryptString($password));

                    return true;
                })
                ->assertSessionHas('_2fa_login.remember', static function ($remember) {
                    static::assertFalse($remember);

                    return true;
                });
        }
    }

    public function test_uses_custom_config(): void
    {
        config([
            'two-factor.login.key' => 'foo',
            'two-factor.login.view' => 'bar',
        ]);

        $view = Mockery::mock(\Illuminate\View\View::class);

        $view->expects('withErrors')->with([
            '2fa_code' => [trans('two-factor::validation.totp_code')]
        ])->andReturnSelf();
        $view->expects('render')->andReturn('baz');
        $view->expects('name')->andReturn('bar');

        View::expects('make')
            ->with('bar', ['input' => '2fa_code'], [])
            ->andReturn($view);

        try {
            Auth2FA::attempt([
                'email' => $this->user->email,
                'password' => 'secret',
            ]);

            static::fail('HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $response = new TestResponse($e->getResponse());

            $response->assertViewIs('bar');
        }
    }

    public function test_builds_attempt(): void
    {
        $guard = Auth::guard('web');

        Auth::expects('guard')->with('qux')->andReturn($guard);

        $view = Mockery::mock(\Illuminate\View\View::class);

        $view->expects('withErrors')->with([
            'baz' => ['bar']
        ])->andReturnSelf();
        $view->expects('render')->andReturn('baz');
        $view->expects('name')->andReturn('bar');
        $view->expects('gatherData')->andReturn(['input' => 'baz']);

        View::expects('make')
            ->with('foo', ['input' => 'baz'], [])
            ->andReturn($view);

        try {
            Auth2FA::view('foo')
                ->message('bar')
                ->input('baz')
                ->sessionKey('quz')
                ->guard('qux')
                ->attempt([
                    'email' => $this->user->email,
                    'password' => 'secret',
                ], true);

            static::fail('HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $response = new TestResponse($e->getResponse());

            $response->assertViewIs('bar')
                ->assertViewHas('input', 'baz')
                ->assertSessionHas('quz.credentials')
                ->assertSessionHas('quz.remember', true);
        }
    }

    public function test_throw_if_no_session_guard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The authentication guard must be a instance of SessionGuard.');

        Auth::expects('guard')->with('qux')->andReturn(Mockery::mock(Guard::class));

        Auth2FA::guard('qux')->attempt([
            'email' => $this->user->email,
            'password' => 'secret',
        ]);
    }

    public function test_authenticates_with_encrypted_credentials_from_session(): void
    {
        try {
            Auth2FA::attempt([
                'email' => $this->user->email,
                'password' => 'secret',
            ]);
        } catch (HttpResponseException) {
            $this->assertGuest();
        }

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->getRecoveryCodes()->first()['code'],
        ]));

        static::assertTrue(Auth2FA::attempt());

        $this->assertAuthenticatedAs($this->user);
    }
}
