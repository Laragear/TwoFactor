<?php

namespace Tests;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use Laragear\TwoFactor\Facades\Auth2FA;
use Mockery;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;

use function app;
use function config;
use function get_class;
use function redirect;
use function today;
use function trans;
use function var_dump;

class TwoFactorLoginHelperTest extends TestCase
{
    use CreatesTwoFactorUser;
    use WithFaker;

    protected array $credentials;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        $this->afterApplicationCreated(function (): void {
            app('config')->set('auth.providers.users.model', UserTwoFactorStub::class);
            $this->travelTo(today());
        });
        $this->afterApplicationCreated(function () {
            $this->credentials = ['email' => $this->user->email, 'password' => 'secret'];
        });

        parent::setUp();
    }

    protected function defineWebRoutes($router): void
    {
        $router->post('login', function (Request $request) {
            try {
                return Auth2FA::attempt($request->only('email', 'password'), $request->filled('remember'))
                    ? 'is authenticated'
                    : 'is unauthenticated';
            } catch (\Throwable $exception) {
                if (! $exception instanceof HttpResponseException) {
                    var_dump([get_class($exception), $exception->getMessage()]);
                }
                throw $exception;
            }
        });
    }

    public function test_authenticates_non_2fa_user_with_credentials(): void
    {
        app('config')->set('auth.providers.users.model', UserStub::class);

        $this->post('login', $this->credentials)->assertSee('is authenticated');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_doesnt_authenticates_non_2fa_user_with_failed_credentials(): void
    {
        app('config')->set('auth.providers.users.model', UserStub::class);

        $this->post('login', [
            'email' => $this->user->email,
            'password' => 'invalid',
        ])->assertSee('is unauthenticated');

        $this->assertGuest();
    }

    public function test_authenticates_2fa_user_with_credentials_and_2fa_disabled(): void
    {
        $this->user->disableTwoFactorAuth();

        $this->post('login', $this->credentials)->assertSee('is authenticated');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_authenticates_2fa_user_with_credentials_and_totp_code(): void
    {
        $this->post('login', $this->credentials + [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ])->assertSeeText('is authenticated');
    }

    public function test_authenticates_2fa_user_with_credentials_and_recovery_code(): void
    {
        $this->post('login', $this->credentials + [
            '2fa_code' => $this->user->getRecoveryCodes()->first()['code'],
        ])->assertSeeText('is authenticated');
    }

    public function test_throws_response_for_2fa_code_if_no_2fa_code(): void
    {
        $this->post('login', $this->credentials)
            ->assertViewIs('two-factor::login')
            ->assertViewHas('input', '2fa_code')
            ->assertViewHas('errors', static function (ViewErrorBag $errors): bool {
                return ! $errors->has('2fa_code');
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

        $this->assertGuest();
    }

    public function test_uses_custom_config(): void
    {
        config([
            'two-factor.login.key' => 'foo',
            'two-factor.login.view' => 'bar',
        ]);

        $view = Mockery::mock(\Illuminate\View\View::class);

        $view->expects('withErrors')->with([])->andReturnSelf();
        $view->expects('render')->andReturn('baz');
        $view->expects('name')->andReturn('bar');

        View::expects('make')
            ->with('bar', ['input' => '2fa_code'], [])
            ->andReturn($view);
        View::expects('share')
            ->withAnyArgs()
            ->andReturnSelf();

        $this->post('login', $this->credentials)->assertViewIs('bar')->assertSee('baz');
    }

    public function test_builds_attempt(): void
    {
        $guard = Auth::guard('web');

        Auth::expects('guard')->with('qux')->andReturn($guard);

        $view = Mockery::mock(\Illuminate\View\View::class);

        $view->expects('withErrors')->with([])->andReturnSelf();
        $view->expects('render')->andReturn('baz');
        $view->expects('name')->andReturn('bar');
        $view->expects('gatherData')->andReturn(['input' => 'baz']);

        View::expects('make')
            ->with('foo', ['input' => 'baz'], [])
            ->andReturn($view);

        Route::post('custom-login', function (Request $request) {
            return Auth2FA::view('foo')
                ->message('bar')
                ->input('baz')
                ->sessionKey('quz')
                ->guard('qux')
                ->attempt($request->only('email', 'password'), true)
                ? 'is authenticated'
                : 'is unauthenticated';
        });

        $this->post('custom-login', $this->credentials)
            ->assertViewIs('bar')
            ->assertViewHas('input', 'baz')
            ->assertSessionHas('quz.credentials')
            ->assertSessionHas('quz.remember', true)
            ->assertSee('baz');
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

    public function test_does_not_encrypts_session_if_already_encrypted(): void
    {
        $store = Mockery::mock(EncryptedStore::class);

        $store->expects('pull')->with('_2fa_login.credentials', [])->andReturn([]);
        $store->expects('pull')->with('_2fa_login.remember', false)->andReturn(false);
        $store->expects('flash')
            ->with('_2fa_login', ['credentials' => $this->credentials, 'remember' => false])
            ->andReturnNull();

        $this->swap('session.store', $store);

        $this->post('login', $this->credentials)
            ->assertViewIs('two-factor::login');
    }

    public function test_authentication_attempt_doesnt_uses_flash_if_disabled(): void
    {
        // Boot the redirector to avoid mocking the session.
        redirect();

        config(['two-factor.login.flash' => false]);

        $store = Mockery::mock(Session::class);

        $store->expects('pull')->with('_2fa_login.credentials', [])->andReturn([]);
        $store->expects('pull')->with('_2fa_login.remember', false)->andReturn(false);
        $store->expects('put')->withArgs(function (string $key, array $input) {
            static::assertSame('_2fa_login', $key);
            static::assertSame($this->user->email, Crypt::decryptString($input['credentials']['email']));
            static::assertSame('secret', Crypt::decryptString($input['credentials']['password']));

            return true;
        })
            ->andReturnNull();

        $this->swap('session.store', $store);

        $this->post('login', $this->credentials)
            ->assertViewIs('two-factor::login');
    }

    public function test_authenticates_with_encrypted_credentials_from_session(): void
    {
        $this->post('login', $this->credentials)
            ->assertViewIs('two-factor::login')
            ->assertSessionHas('_2fa_login');

        $this->assertGuest();

        $this->post('login', ['2fa_code' => $this->user->makeTwoFactorCode()])
            ->assertSee('is authenticated')
            ->assertSessionMissing('_2fa_login');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_reflashes_credentials_if_2fa_code_fails(): void
    {
        $this->session([
            '_token' => 'u7uPAbLaMXa5AFhFotkP9aDoi71WynN9dogAXIxS',
            '_2fa_login' => [
                'credentials' => [
                    'email' => 'eyJpdiI6IkZ1NXBzL2d5U1U3M0swYVBMMi9ObGc9PSIsInZhbHVlIjoia2xZZmtJTTVwUEdPKzFxWXF2MXc4QT09IiwibWFjIjoiMzJmZjA5MTYyOGNiNTZkYTk4M2JiNWE0YzgzZjk0MDBjZTY4M2I1YjdhMTczOGZhOGFiZDk3YmZkZmE5YjViYiIsInRhZyI6IiJ9',
                    'password' => 'eyJpdiI6IkNTaEEzaDgrNFdNQkF4SmRsYzA5RHc9PSIsInZhbHVlIjoiMlVOWS9vSVFuclJkTEx2MXphck03dz09IiwibWFjIjoiYjU5ZWM2N2ViNzAxMjZkYjJmNzhhOWRiZTExNWRjZjM2ODNmMmM0OGRiN2RhYzQ3MzZkOGQxZjI3YjY3YzJiMyIsInRhZyI6IiJ9',
                ],
                'remember' => false,
            ],
            '_flash' => [
                'new' => [],
                'old' => [
                    0 => '_2fa_login',
                ],
            ],
        ]);

        $this->post('login', ['2fa_code' => '000000'])
            ->assertViewIs('two-factor::login')
            ->assertViewHas('errors', static function (ViewErrorBag $bag): bool {
                static::assertTrue($bag->has('2fa_code'));
                static::assertSame(
                    $bag->first('2fa_code'),
                    trans('two-factor::validation.totp_code', ['attribute' => '2fa_code'])
                );

                return true;
            })
            ->assertSessionHas('_2fa_login');

        $this->assertGuest();
    }

    public function test_throws_redirection_on_failure(): void
    {
        $this->app->make('router')->post('login-with-redirect', function (Request $request) {
            try {
                return Auth2FA::redirect('foo')->attempt($request->only('email', 'password'))
                    ? 'is authenticated'
                    : 'is unauthenticated';
            } catch (\Throwable $exception) {
                if (! $exception instanceof HttpResponseException) {
                    var_dump([get_class($exception), $exception->getMessage()]);
                }

                throw $exception;
            }
        });

        $this->post('login-with-redirect', $this->credentials)
            ->assertRedirect('foo')
            ->assertSessionHasInput('input', '2fa_code')
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

        $this->assertGuest();
    }
}
