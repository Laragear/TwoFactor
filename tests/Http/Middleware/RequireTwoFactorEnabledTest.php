<?php

namespace Tests\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTwoFactorUser;
use Tests\Stubs\UserStub;
use Tests\TestCase;

class RequireTwoFactorEnabledTest extends TestCase
{
    use RefreshDatabase;
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

        $router->get('test', function () {
            return 'ok';
        })->middleware('auth', '2fa.enabled');

        $router->get('notice', function () {
            return '2fa.notice';
        })->middleware('auth')->name('2fa.notice');

        $router->get('custom', function () {
            return 'custom-notice';
        })->middleware('auth')->name('custom-notice');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();
    }

    public function test_guest_cant_access(): void
    {
        $this->get('test')->assertRedirect('login');

        $this->getJson('test')->assertJson(['message' => 'Unauthenticated.'])->assertStatus(401);
    }

    public function test_user_no_2fa_can_access(): void
    {
        $this->actingAs(UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]));

        $this->get('test')->assertSee('ok');

        $this->getJson('test')->assertSee('ok')->assertOk();
    }

    public function test_user_2fa_not_enabled_cant_access(): void
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('test')->assertSee('2fa.notice');

        $this->getJson('test')
            ->assertJson(['message' => 'You need to enable Two-Factor Authentication.'])
            ->assertForbidden();
    }

    public function test_user_2fa_enabled_access(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()->get('test')->assertSee('ok');

        $this->getJson('test')->assertSee('ok');
    }

    public function test_redirects_to_custom_notice(): void
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('custom')->assertSee('custom-notice');
    }
}
