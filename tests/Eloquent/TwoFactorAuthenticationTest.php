<?php

namespace Tests\Eloquent;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Laragear\TwoFactor\Models\TwoFactorAuthentication;
use ParagonIE\ConstantTime\Base32;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;
use Tests\TestCase;

use function rawurlencode;

class TwoFactorAuthenticationTest extends TestCase
{
    protected const SECRET = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3';

    protected function tearDown(): void
    {
        TwoFactorAuthentication::generateRecoveryCodesUsing();

        parent::tearDown();
    }

    public function test_returns_authenticatable(): void
    {
        $user = UserTwoFactorStub::create([
            'name' => 'foo',
            'email' => 'foo@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $user->twoFactorAuth()->save(
            $tfa = TwoFactorAuthentication::factory()->make()
        );

        static::assertInstanceOf(MorphTo::class, $tfa->authenticatable());
        static::assertTrue($user->is($tfa->authenticatable));
    }

    public function test_lowercases_algorithm(): void
    {
        $tfa = TwoFactorAuthentication::factory()
            ->withRecovery()->withSafeDevices()
            ->make([
                'algorithm' => 'AbCdE2',
            ]);

        static::assertEquals('abcde2', $tfa->algorithm);
    }

    public function test_is_enabled_and_is_disabled(): void
    {
        $tfa = new TwoFactorAuthentication();

        static::assertTrue($tfa->isDisabled());
        static::assertFalse($tfa->isEnabled());

        $tfa->enabled_at = now();

        static::assertTrue($tfa->isEnabled());
        static::assertFalse($tfa->isDisabled());
    }

    public function test_flushes_authentication(): void
    {
        $tfa = TwoFactorAuthentication::factory()
            ->withRecovery()->withSafeDevices()
            ->create([
                'authenticatable_type' => 'test',
                'authenticatable_id' => 9,
            ]);

        static::assertNotNull($old = $tfa->shared_secret);
        static::assertNotNull($tfa->enabled_at);
        static::assertNotNull($label = $tfa->label);
        static::assertNotNull($tfa->digits);
        static::assertNotNull($tfa->seconds);
        static::assertNotNull($tfa->window);
        static::assertNotNull($tfa->algorithm);
        static::assertNotNull($tfa->recovery_codes_generated_at);
        static::assertNotNull($tfa->safe_devices);

        $tfa->flushAuth()->save();

        static::assertNotEquals($old, $tfa->shared_secret);
        static::assertNull($tfa->enabled_at);
        static::assertNotNull($tfa->label);
        static::assertEquals($label, $tfa->label);
        static::assertEquals(config('two-factor.totp.digits'), $tfa->digits);
        static::assertEquals(config('two-factor.totp.seconds'), $tfa->seconds);
        static::assertEquals(config('two-factor.totp.window'), $tfa->window);
        static::assertEquals(config('two-factor.totp.algorithm'), $tfa->algorithm);
        static::assertNull($tfa->recovery_codes_generated_at);
        static::assertNull($tfa->safe_devices);
    }

    public function test_generates_random_secret(): void
    {
        $secret = TwoFactorAuthentication::generateRandomSecret();

        static::assertEquals(config('two-factor.secret_length'), strlen(Base32::decodeUpper($secret)));
    }

    public function test_makes_code(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
        ]);

        $this->travelTo(Date::create(2020, 1, 1, 20, 29, 59));
        static::assertEquals('779186', $tfa->makeCode());
        static::assertEquals('716347', $tfa->makeCode('now', 1));

        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 0));
        static::assertEquals('716347', $tfa->makeCode());
        static::assertEquals('779186', $tfa->makeCode('now', -1));

        for ($i = 0; $i < 30; $i++) {
            $this->travelTo(Date::create(2020, 1, 1, 20, 30, $i));
            static::assertEquals('716347', $tfa->makeCode());
        }

        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 31));
        static::assertEquals('133346', $tfa->makeCode());

        static::assertEquals('818740', $tfa->makeCode(
            Date::create(2020, 1, 1, 1, 1, 1))
        );

        static::assertEquals('976814', $tfa->makeCode('4th february 2020'));
    }

    public function test_makes_code_for_timestamp(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
        ]);

        static::assertEquals('566278', $tfa->makeCode(1581300000));
        static::assertTrue($tfa->validateCode('566278', 1581300000));
    }

    public function test_validate_code(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
            'window' => 0,
        ]);

        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 0));

        static::assertEquals('716347', $code = $tfa->makeCode());
        static::assertTrue($tfa->validateCode($tfa->makeCode()));

        $this->travelTo(Date::create(2020, 1, 1, 20, 29, 59));
        static::assertFalse($tfa->validateCode($code));

        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 31));
        static::assertFalse($tfa->validateCode($code));
    }

    public function test_validate_code_with_window(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
            'window' => 1,
        ]);

        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 0));

        static::assertEquals('716347', $code = $tfa->makeCode());
        static::assertTrue($tfa->validateCode($tfa->makeCode()));

        Cache::getStore()->flush();
        $this->travelTo(Date::create(2020, 1, 1, 20, 29, 59));
        static::assertFalse($tfa->validateCode($code));

        Cache::getStore()->flush();
        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 31));
        static::assertTrue($tfa->validateCode($code));

        Cache::getStore()->flush();
        $this->travelTo(Date::create(2020, 1, 1, 20, 30, 59));
        static::assertTrue($tfa->validateCode($code));

        Cache::getStore()->flush();
        $this->travelTo(Date::create(2020, 1, 1, 20, 31, 0));
        static::assertFalse($tfa->validateCode($code));
    }

    public function test_contains_unused_recovery_codes(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make();

        static::assertTrue($tfa->containsUnusedRecoveryCodes());

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'recovery_codes' => null,
        ]);

        static::assertFalse($tfa->containsUnusedRecoveryCodes());

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'recovery_codes' => collect([
                [
                    'code' => '2G5oP36',
                    'used_at' => 'anything not null',
                ],
            ]),
        ]);

        static::assertFalse($tfa->containsUnusedRecoveryCodes());
    }

    public function test_generates_recovery_codes(): void
    {
        $codes = TwoFactorAuthentication::generateRecoveryCodes(13, 7);

        static::assertCount(13, $codes);

        $codes->each(function ($item) {
            static::assertEquals(7, strlen($item['code']));
            static::assertNull($item['used_at']);
        });
    }

    public function test_generates_random_safe_device_remember_token(): void
    {
        static::assertEquals(100, strlen(TwoFactorAuthentication::generateDefaultTwoFactorRemember()));
    }

    public function test_serializes_to_string(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
        ]);

        static::assertEquals(static::SECRET, $tfa->toString());
        static::assertEquals(static::SECRET, $tfa->__toString());
        static::assertEquals(static::SECRET, (string) $tfa);
    }

    public function test_serializes_to_grouped_string(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
        ]);

        static::assertEquals('KS72 XBTN 5PEB GX2I WBMV W44L XHPA Q7L3', $tfa->toGroupedString());
    }

    public function test_serializes_to_uri(): void
    {
        $this->app->make('config')->set('two-factor.issuer', 'quz');

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $encode = rawurlencode($tfa->label);

        $uri = "otpauth://totp/$encode?issuer=quz&label=$encode&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14";

        static::assertEquals($uri, $tfa->toUri());
    }

    public function test_serializes_to_qr_and_renders_to_qr(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label' => 'quz:test@foo.com',
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $stub = class_exists('\BaconQrCode\Renderer\Eye\PointyEye')
            ? __DIR__.'/../Stubs/QrStub-v3.svg'
            : __DIR__.'/../Stubs/QrStub.svg';

        static::assertStringEqualsFile($stub, $tfa->toQr());
        static::assertStringEqualsFile($stub, $tfa->render());
    }

    public function test_serializes_to_qr_and_renders_to_qr_with_custom_values(): void
    {
        $this->app->make('config')->set([
            'two-factor.qr_code' => [
                'size' => 600,
                'margin' => 10,
            ],
        ]);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label' => 'quz:test@foo.com',
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $stub = class_exists('\BaconQrCode\Renderer\Eye\PointyEye')
            ? __DIR__.'/../Stubs/CustomQrStub-v3.svg'
            : __DIR__.'/../Stubs/CustomQrStub.svg';

        static::assertStringEqualsFile($stub, $tfa->toQr());
        static::assertStringEqualsFile($stub, $tfa->render());
    }

    public function test_serializes_uri_to_json(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label' => 'quz:test@foo.com',
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $uri = '"otpauth:\/\/totp\/quz%3Atest%40foo.com?issuer=quz&label=quz%3Atest%40foo.com&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14"';

        static::assertJson($tfa->toJson());
        static::assertEquals($uri, $tfa->toJson());
    }

    public function test_uses_app_name_as_issuer(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label' => 'Laravel:test@foo.com',
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $uri = 'otpauth://totp/Laravel%3Atest%40foo.com?issuer=Laravel&label=Laravel%3Atest%40foo.com&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14';

        static::assertSame($uri, $tfa->toUri());
    }

    public function test_changes_issuer(): void
    {
        $this->app->make('config')->set('two-factor.issuer', 'foo bar');

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => static::SECRET,
            'algorithm' => 'sHa256',
            'digits' => 14,
        ]);

        $encode = rawurlencode($tfa->label);
        $uri = "otpauth://totp/$encode?issuer=foo%20bar&label=$encode&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14";

        static::assertSame($uri, $tfa->toUri());
    }

    public function test_uses_custom_generator(): void
    {
        $i = 0;

        TwoFactorAuthentication::generateRecoveryCodesUsing(function ($length, $item, $amount) use (&$i) {
            static::assertSame(8, $length);
            static::assertSame(++$i, $item);
            static::assertSame(10, $amount);

            return 'foo';
        });

        TwoFactorAuthentication::factory()->make()->recovery_codes->each(static function (array $code): void {
            static::assertSame('foo', $code['code']);
        });
    }
}
