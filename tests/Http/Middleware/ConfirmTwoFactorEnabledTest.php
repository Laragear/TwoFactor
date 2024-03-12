<?php

namespace Tests\Http\Middleware;

use Illuminate\Support\Facades\Date;
use Laragear\TwoFactor\Http\Controllers\ConfirmTwoFactorCodeController;
use Tests\CreatesTwoFactorUser;
use Tests\Stubs\UserStub;
use Tests\TestCase;

use function now;
use function trans;

class ConfirmTwoFactorEnabledTest extends TestCase
{
    use CreatesTwoFactorUser;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);

        parent::setUp();
    }

    protected function defineWebRoutes($router)
    {
        $router->get('login', function () {
            return 'login';
        })->name('login');

        $router->get('intended', function () {
            return 'ok';
        })->name('intended')->middleware('auth:web', '2fa.confirm');

        $router->get('confirm', [ConfirmTwoFactorCodeController::class, 'form'])->name('2fa.confirm');
        $router->post('confirm', [ConfirmTwoFactorCodeController::class, 'confirm']);
    }

    public function test_guest_cant_access(): void
    {
        $this->assertGuest();

        $this->get('intended')->assertRedirect('login');
    }

    public function test_continues_if_user_is_not_2fa_instance(): void
    {
        $this->actingAs(UserStub::create([
            'name' => 'test',
            'email' => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]));

        $this->followingRedirects()->get('intended')->assertSee('ok');
        $this->getJson('intended')->assertSee('ok');
    }

    public function test_continues_if_user_is_2fa_but_not_activated(): void
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('intended')->assertSee('ok');
        $this->getJson('intended')->assertSee('ok');
    }

    public function test_asks_for_confirmation_if_forced(): void
    {
        $this->app['router']->get('intended_force', function () {
            return 'ok';
        })->name('intended')->middleware('web', 'auth', '2fa.confirm:2fa.confirm,true');

        $this->actingAs($this->user);

        $sessionKey = $this->app->make('config')->get('two-factor.confirm.key').'confirm.expires_at';

        $this->session([$sessionKey => now()->addHour()->getTimestamp()]);

        $this->getJson('intended_force')->assertJson(['message' => trans('two-factor::messages.required')]);
        $this->get('intended_force')->assertRedirect('confirm');
    }

    public function test_asks_for_confirmation_if_user_2fa_but_not_already_confirmed(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()->get('intended')->assertViewIs('two-factor::confirm');

        $this->getJson('intended')->assertJson(['message' => trans('two-factor::messages.required')]);
    }

    public function test_redirects_to_intended_when_code_valid(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertSessionMissing('_2fa.confirm.expires_at')
            ->assertViewIs('two-factor::confirm');

        $this->followingRedirects()
            ->post('confirm', [
                '2fa_code' => $this->user->makeTwoFactorCode(),
            ])
            ->assertSessionHas('_2fa.confirm.expires_at')
            ->assertSee('ok');

        $this->followingRedirects()
            ->get('intended')
            ->assertSee('ok');
    }

    public function test_returns_ok_on_json_response(): void
    {
        $this->actingAs($this->user);

        $this->getJson('intended')
            ->assertSessionMissing('_2fa.confirm.expires_at')
            ->assertJson(['message' => 'Two-Factor Authentication is required.'])
            ->assertStatus(403);

        $this->postJson('confirm', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ])
            ->assertOk()
            ->assertSessionHas('_2fa.confirm.expires_at')
            ->assertJson(['message' => 'The 2FA code has been validated successfully.']);
    }

    public function test_returns_validation_error_when_code_invalid(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('two-factor::confirm');

        $this->post('confirm', [
            '2fa_code' => 'invalid',
        ])
            ->assertSessionHasErrors();
    }

    public function test_bypasses_check_if_not_expired(): void
    {
        $this->travelTo($now = Date::create(2020, 04, 01, 20, 20));

        $this->actingAs($this->user);

        $this->session([
            '_2fa.confirm.expires_at' => $now->getTimestamp(),
        ]);

        $this->followingRedirects()
            ->get('intended')
            ->assertSee('ok');

        $this->session([
            '_2fa.confirm.expires_at' => $now->getTimestamp() - 1,
        ]);

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('two-factor::confirm');
    }

    public function test_throttles_totp(): void
    {
        $this->travelTo(Date::create(2020, 04, 01, 20, 20));

        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('two-factor::confirm');

        for ($i = 0; $i < 60; $i++) {
            $this->post('confirm', [
                '2fa_code' => 'invalid',
            ])->assertSessionHasErrors();
        }

        $this->post('confirm', [
            '2fa_code' => 'invalid',
        ])->assertStatus(429);
    }

    public function test_routes_to_alternate_named_route(): void
    {
        $this->app['router']->get('intended_to_foo', function () {
            return 'ok';
        })->name('intended')->middleware('web', 'auth', '2fa.confirm:foo');

        $this->app['router']->get('foo', function () {
            return 'foo';
        })->name('foo');

        $this->actingAs($this->user);

        $this->get('intended_to_foo')
            ->assertRedirect('foo');
    }
}
