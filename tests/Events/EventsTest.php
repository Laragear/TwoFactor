<?php

namespace Tests\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laragear\TwoFactor\Events\TwoFactorDisabled;
use Laragear\TwoFactor\Events\TwoFactorEnabled;
use Laragear\TwoFactor\Events\TwoFactorRecoveryCodesDepleted;
use Laragear\TwoFactor\Events\TwoFactorRecoveryCodesGenerated;
use Tests\CreatesTwoFactorUser;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTwoFactorUser;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        parent::setUp();
    }

    public function test_fires_two_factor_enabled_event(): void
    {
        $event = Event::fake();

        $this->user->disableTwoFactorAuth();

        $this->user->enableTwoFactorAuth();

        $event->assertDispatchedTimes(TwoFactorEnabled::class);
        $event->assertDispatched(TwoFactorEnabled::class, function (TwoFactorEnabled $event): bool {
            return $this->user->is($event->user);
        });
    }

    public function test_fires_two_factor_disabled_event(): void
    {
        $event = Event::fake();

        $this->user->disableTwoFactorAuth();

        $event->assertDispatchedTimes(TwoFactorDisabled::class);
        $event->assertDispatched(TwoFactorDisabled::class, function (TwoFactorDisabled $event): bool {
            return $this->user->is($event->user);
        });
    }

    public function test_fires_two_factor_recovery_codes_depleted(): void
    {
        $event = Event::fake();

        $code = Str::random(8);

        $this->user->twoFactorAuth->recovery_codes = Collection::times(1, static function () use ($code): array {
            return [
                'code' => $code,
                'used_at' => null,
            ];
        });

        $this->user->twoFactorAuth->save();

        $this->user->validateTwoFactorCode($code);

        $event->assertDispatched(
            TwoFactorRecoveryCodesDepleted::class,
            function (TwoFactorRecoveryCodesDepleted $event): bool {
                return $this->user->is($event->user);
            }
        );
    }

    public function test_fires_two_factor_recovery_codes_generated(): void
    {
        $event = Event::fake();

        $this->user->generateRecoveryCodes();

        $event->assertDispatched(
            TwoFactorRecoveryCodesGenerated::class,
            function (TwoFactorRecoveryCodesGenerated $event): bool {
                return $this->user->is($event->user);
            }
        );
    }
}
