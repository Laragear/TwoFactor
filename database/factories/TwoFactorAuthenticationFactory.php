<?php

namespace Database\Factories\Laragear\TwoFactor;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laragear\TwoFactor\Models\TwoFactorAuthentication;

use function config;

/**
 * @method \Laragear\TwoFactor\Models\TwoFactorAuthentication make($attributes = [], ?Model $parent = null)
 * @method \Laragear\TwoFactor\Models\TwoFactorAuthentication create($attributes = [], ?Model $parent = null)
 */
class TwoFactorAuthenticationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TwoFactorAuthentication::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $config = config('two-factor');

        $array = array_merge([
            'shared_secret' => TwoFactorAuthentication::generateRandomSecret(),
            'enabled_at' => $this->faker->dateTimeBetween('-1 year'),
            'label' => $config['issuer'].':'.$this->faker->freeEmail,
        ], $config['totp']);

        [$enabled, $amount, $length] = array_values($config['recovery']);

        if ($enabled) {
            $array['recovery_codes'] = TwoFactorAuthentication::generateRecoveryCodes($amount, $length);
            $array['recovery_codes_generated_at'] = $this->faker->dateTimeBetween('-1 year');
        }

        return $array;
    }

    /**
     * Returns a model with unused recovery codes.
     *
     * @return \Database\Factories\Laragear\TwoFactor\TwoFactorAuthenticationFactory
     */
    public function withRecovery(): static
    {
        [
            'two-factor.recovery.codes' => $amount,
            'two-factor.recovery.length' => $length
        ] = config()->get(['two-factor.recovery.codes', 'two-factor.recovery.length']);

        return $this->state([
            'recovery_codes' => TwoFactorAuthentication::generateRecoveryCodes($amount, $length),
            'recovery_codes_generated_at' => $this->faker->dateTimeBetween('-1 years'),
        ]);
    }

    /**
     * Returns an authentication with a list of safe devices.
     *
     * @return \Database\Factories\Laragear\TwoFactor\TwoFactorAuthenticationFactory
     */
    public function withSafeDevices(): static
    {
        $max = config('two-factor.safe_devices.max_devices');

        return $this->state([
            'safe_devices' => Collection::times($max, function ($step) use ($max) {
                $expiration_days = config('two-factor.safe_devices.expiration_days');

                $added_at = $max !== $step
                    ? now()
                    : $this->faker->dateTimeBetween(now()->subDays($expiration_days * 2),
                        now()->subDays($expiration_days));

                return [
                    '2fa_remember' => TwoFactorAuthentication::generateDefaultTwoFactorRemember(),
                    'ip' => $this->faker->ipv4,
                    'added_at' => $added_at,
                ];
            }),
        ]);
    }

    /**
     * Returns an enabled authentication.
     *
     * @return \Database\Factories\Laragear\TwoFactor\TwoFactorAuthenticationFactory
     */
    public function enabled(): static
    {
        return $this->state([
            'enabled_at' => null,
        ]);
    }
}
