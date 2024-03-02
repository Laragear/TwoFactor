<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Laragear\TwoFactor\Events\TwoFactorDisabled;
use Laragear\TwoFactor\Events\TwoFactorEnabled;
use Laragear\TwoFactor\Events\TwoFactorRecoveryCodesDepleted;
use Laragear\TwoFactor\Events\TwoFactorRecoveryCodesGenerated;
use Laragear\TwoFactor\Models\TwoFactorAuthentication;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;

class TwoFactorAuthenticationTest extends TestCase
{
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;
    use WithFaker;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);

        parent::setUp();
    }

    public function test_hides_relation_from_serialization(): void
    {
        $array = $this->user->toArray();

        static::assertArrayNotHasKey('two_factor_auth', $array);
        static::assertArrayNotHasKey('twoFactorAuth', $array);
    }

    public function test_returns_two_factor_relation(): void
    {
        static::assertInstanceOf(TwoFactorAuthentication::class, $this->user->twoFactorAuth);
    }

    public function test_has_two_factor_enabled(): void
    {
        static::assertTrue($this->user->hasTwoFactorEnabled());

        $this->user->disableTwoFactorAuth();

        static::assertFalse($this->user->hasTwoFactorEnabled());
    }

    public function test_disables_two_factor_authentication(): void
    {
        $events = Event::fake();

        $this->user->disableTwoFactorAuth();
        static::assertFalse($this->user->hasTwoFactorEnabled());

        $events->assertDispatched(TwoFactorDisabled::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_enables_two_factor_authentication(): void
    {
        $events = Event::fake();

        $this->user->enableTwoFactorAuth();
        static::assertTrue($this->user->hasTwoFactorEnabled());

        $events->assertDispatched(TwoFactorEnabled::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_creates_two_factor_authentication(): void
    {
        $events = Event::fake();
        $user = UserTwoFactorStub::create([
            'name' => 'bar',
            'email' => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $this->assertDatabaseMissing(TwoFactorAuthentication::class, [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $user->getKey()],
        ]);

        $tfa = $user->createTwoFactorAuth();

        $this->assertInstanceOf(TwoFactorAuthentication::class, $tfa);
        static::assertTrue($tfa->exists);
        static::assertFalse($user->hasTwoFactorEnabled());

        $this->assertDatabaseHas(TwoFactorAuthentication::class, [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $user->getKey()],
            ['enabled_at', null],
        ]);

        $events->assertNotDispatched(TwoFactorEnabled::class);
    }

    public function test_creates_two_factor_flushes_old_auth(): void
    {
        $this->user->twoFactorAuth->safe_devices = collect([1, 2, 3]);
        $this->user->twoFactorAuth->save();

        static::assertNotEmpty($this->user->getRecoveryCodes());
        static::assertNotNull($this->user->twoFactorAuth->recovery_codes_generated_at);
        static::assertNotEmpty($this->user->safeDevices());
        static::assertNotNull($this->user->twoFactorAuth->enabled_at);

        $this->user->createTwoFactorAuth();

        static::assertEmpty($this->user->getRecoveryCodes());
        static::assertNull($this->user->twoFactorAuth->recovery_codes_generated_at);
        static::assertEmpty($this->user->safeDevices());
        static::assertNull($this->user->twoFactorAuth->enabled_at);
    }

    public function test_rewrites_when_creating_two_factor_authentication(): void
    {
        $this->assertDatabaseHas(TwoFactorAuthentication::class, [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $this->user->getKey()],
            ['enabled_at', '!=', null],
        ]);

        static::assertTrue($this->user->hasTwoFactorEnabled());

        $old = $this->user->twoFactorAuth->shared_secret;

        $this->user->createTwoFactorAuth();

        static::assertFalse($this->user->hasTwoFactorEnabled());
        static::assertNotEquals($old, $this->user->twoFactorAuth->shared_secret);
    }

    public function test_new_user_confirms_two_factor_successfully(): void
    {
        $event = Event::fake();

        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $user = UserTwoFactorStub::create([
            'name' => 'bar',
            'email' => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $user->createTwoFactorAuth();

        $code = $user->makeTwoFactorCode();

        static::assertTrue($user->confirmTwoFactorAuth($code));
        static::assertTrue($user->hasTwoFactorEnabled());
        static::assertFalse($user->validateTwoFactorCode($code));

        Cache::getStore()->flush();
        static::assertTrue($user->validateTwoFactorCode($code));

        static::assertEquals($now, $user->twoFactorAuth->enabled_at);

        $event->assertDispatched(TwoFactorRecoveryCodesGenerated::class, function ($event) use ($user) {
            return $user->is($event->user);
        });
    }

    public function test_issuer_falls_back_to_application_name(): void
    {
        $this->app->make('config')->set([
            'app.name' => 'foo',
            'two-factor.issuer' => '',
        ]);

        static::assertSame('foo:foo@test.com', $this->user->createTwoFactorAuth()->label);
    }

    public function test_throws_if_issuer_is_empty(): void
    {
        $this->app->make('config')->set([
            'app.name' => '',
            'two-factor.issuer' => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The TOTP issuer cannot be empty.');

        $this->user->createTwoFactorAuth();
    }

    public function test_throws_if_user_identifier_is_empty(): void
    {
        $this->user->email = '';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The TOTP User Identifier cannot be empty.');

        $this->user->createTwoFactorAuth();
    }

    public function test_confirms_twice_but_doesnt_change_the_secret(): void
    {
        $event = Event::fake();

        $old_now = $this->user->twoFactorAuth->enabled_at;

        $this->travelTo(Date::create(2020, 01, 01, 18, 30));

        $secret = $this->user->twoFactorAuth->shared_secret;

        $code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->confirmTwoFactorAuth($code));

        $this->user->refresh();

        static::assertTrue($this->user->hasTwoFactorEnabled());
        static::assertTrue($this->user->validateTwoFactorCode($code));
        static::assertEquals($old_now, $this->user->twoFactorAuth->enabled_at);
        static::assertEquals($secret, $this->user->twoFactorAuth->shared_secret);

        $event->assertNotDispatched(TwoFactorRecoveryCodesGenerated::class);
    }

    public function test_doesnt_confirm_two_factor_auth_with_old_recovery_code(): void
    {
        $recovery_code = $this->user->twoFactorAuth->recovery_codes->random();

        $code = $recovery_code['code'];

        $this->user->createTwoFactorAuth();

        static::assertFalse($this->user->confirmTwoFactorAuth($code));
    }

    public function test_old_user_confirms_new_two_factor_successfully(): void
    {
        $event = Event::fake();

        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $old_code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($old_code));

        $this->user->createTwoFactorAuth();

        $new_code = $this->user->makeTwoFactorCode();

        static::assertFalse($this->user->confirmTwoFactorAuth($old_code));
        static::assertFalse($this->user->hasTwoFactorEnabled());

        Cache::getStore()->flush();
        static::assertTrue($this->user->confirmTwoFactorAuth($new_code));
        static::assertTrue($this->user->hasTwoFactorEnabled());

        Cache::getStore()->flush();
        static::assertFalse($this->user->validateTwoFactorCode($old_code));
        static::assertTrue($this->user->validateTwoFactorCode($new_code));

        static::assertEquals($now, $this->user->twoFactorAuth->enabled_at);
        static::assertEquals($now, $this->user->twoFactorAuth->updated_at);

        $event->assertDispatched(TwoFactorRecoveryCodesGenerated::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_validates_two_factor_code(): void
    {
        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($code));
    }

    public function test_validates_two_factor_code_with_recovery_code(): void
    {
        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $recovery_code = $this->user->getRecoveryCodes()->random()['code'];

        $code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($code));

        static::assertTrue($this->user->validateTwoFactorCode($recovery_code));
        static::assertFalse($this->user->validateTwoFactorCode($recovery_code));
    }

    public function test_doesnt_validates_two_factor_code_with_recovery_code_when_excluded(): void
    {
        $this->travelTo(Date::create(2020, 01, 01, 18, 30));

        $recoveryCode = $this->user->getRecoveryCodes()->random()['code'];

        $code = $this->user->makeTwoFactorCode();

        static::assertFalse($this->user->validateTwoFactorCode($recoveryCode, false));
        static::assertTrue($this->user->validateTwoFactorCode($code, false));
    }

    public function test_doesnt_validates_if_two_factor_auth_is_disabled(): void
    {
        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $recovery_code = $this->user->getRecoveryCodes()->random()['code'];

        $code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($code));

        $this->user->disableTwoFactorAuth();

        static::assertFalse($this->user->validateTwoFactorCode($code));
        static::assertFalse($this->user->validateTwoFactorCode($recovery_code));
    }

    public function test_fires_recovery_codes_depleted(): void
    {
        $event = Event::fake();

        foreach ($this->user->getRecoveryCodes() as $item) {
            static::assertTrue($this->user->validateTwoFactorCode($item['code']));
        }

        foreach ($this->user->getRecoveryCodes() as $item) {
            static::assertFalse($this->user->validateTwoFactorCode($item['code']));
        }

        $event->assertDispatchedTimes(TwoFactorRecoveryCodesDepleted::class, 1);
        $event->assertDispatched(TwoFactorRecoveryCodesDepleted::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_safe_device(): void
    {
        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip = $this->faker->ipv4,
        ]);

        static::assertEmpty($this->user->safeDevices());

        $this->user->addSafeDevice($request);

        static::assertCount(1, $this->user->safeDevices());
        static::assertEquals($ip, $this->user->safeDevices()->first()['ip']);
        static::assertEquals(1577903400, $this->user->safeDevices()->first()['added_at']);
    }

    public function test_oldest_safe_device_discarded_when_adding_maximum(): void
    {
        $this->travelTo(Date::create(2020, 01, 01, 18));

        $this->user->addSafeDevice(
            Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => $old_request_ip = $this->faker->ipv4,
            ])
        );

        static::assertTrue($this->user->safeDevices()->contains('ip', $old_request_ip));

        $max_devices = $this->app->make('config')->get('two-factor.safe_devices.max_devices');

        for ($i = 0; $i <= $max_devices; $i++) {
            $this->travelTo(Date::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        static::assertCount(3, $this->user->safeDevices());

        static::assertFalse($this->user->safeDevices()->contains('ip', $old_request_ip));
    }

    public function test_flushes_safe_devices(): void
    {
        $max_devices = $this->app->make('config')->get('two-factor.safe_devices.max_devices') + 4;

        for ($i = 0; $i < $max_devices; $i++) {
            $this->travelTo(Date::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        static::assertCount(3, $this->user->safeDevices());

        $this->user->flushSafeDevices();

        static::assertEmpty($this->user->safeDevices());
    }

    public function test_is_safe_device_and_safe_with_other_ip(): void
    {
        $max_devices = $this->app->make('config')->get('two-factor.safe_devices.max_devices');

        for ($i = 0; $i < $max_devices; $i++) {
            $this->travelTo(Date::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '_2fa_remember' => $this->user->safeDevices()->random()['2fa_remember'],
        ], [], [
            'REMOTE_ADDR' => $this->faker->ipv4,
        ]);

        static::assertTrue($this->user->isSafeDevice($request));
        static::assertFalse($this->user->isNotSafeDevice($request));
    }

    public function test_not_safe_device_if_remember_code_doesnt_match(): void
    {
        $max_devices = $this->app->make('config')->get('two-factor.safe_devices.max_devices');

        for ($i = 0; $i < $max_devices; $i++) {
            $this->travelTo($now = Date::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $ip = $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '_2fa_remember' => 'anything',
        ], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        static::assertFalse($this->user->isSafeDevice($request));
        static::assertTrue($this->user->isNotSafeDevice($request));
    }

    public function test_not_safe_device_if_expired(): void
    {
        $max_devices = $this->app->make('config')->get('two-factor.safe_devices.max_devices');

        $this->travelTo($now = Date::create(2020, 01, 01, 18, 30));

        for ($i = 0; $i < $max_devices; $i++) {
            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '_2fa_remember' => $this->user->safeDevices()->random()['2fa_remember'],
        ], [], [
            'REMOTE_ADDR' => $this->faker->ipv4,
        ]);

        static::assertTrue($this->user->isSafeDevice($request));
        static::assertFalse($this->user->isNotSafeDevice($request));

        $this->travelTo($now->clone()->addDays($this->app->make('config')->get('two-factor.safe_devices.expiration_days'))->subSecond());

        static::assertTrue($this->user->isSafeDevice($request));
        static::assertFalse($this->user->isNotSafeDevice($request));

        $this->travelTo($now->clone()->addDays($this->app->make('config')->get('two-factor.safe_devices.expiration_days'))->addSecond());

        static::assertTrue($this->user->isNotSafeDevice($request));
        static::assertFalse($this->user->isSafeDevice($request));
    }

    public function test_unique_code_works_only_one_time(): void
    {
        $this->travelTo(Date::create(2020, 01, 01, 18, 30, 0));

        $code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($code));
        static::assertFalse($this->user->validateTwoFactorCode($code));

        $this->travelTo(Date::create(2020, 01, 01, 18, 30, 59));

        $new_code = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($new_code));
        static::assertFalse($this->user->validateTwoFactorCode($code));
    }

    public function test_unique_code_works_only_one_time_with_extended_window(): void
    {
        $this->user->twoFactorAuth->window = 5;
        $this->user->twoFactorAuth->save();

        $this->travelTo(Date::create(2020, 01, 01, 18, 30, 0));

        $old = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($old));
        static::assertFalse($this->user->validateTwoFactorCode($old));

        $this->travelTo(Date::create(2020, 01, 01, 18, 32, 29));

        $new = $this->user->makeTwoFactorCode();

        static::assertTrue($this->user->validateTwoFactorCode($new));
        static::assertFalse($this->user->validateTwoFactorCode($new));
    }

    public function test_unique_code_works_only_one_time_in_extended_time(): void
    {
        $this->travelTo(Date::create(2020, 01, 01, 18, 30, 20));

        $code = $this->user->makeTwoFactorCode();

        $this->travelTo(Date::create(2020, 01, 01, 18, 30, 59));

        static::assertTrue($this->user->validateTwoFactorCode($code));
        static::assertFalse($this->user->validateTwoFactorCode($code));
    }
}
