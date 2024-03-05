<?php

namespace Laragear\TwoFactor\Models;

use Database\Factories\Laragear\TwoFactor\TwoFactorAuthenticationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laragear\TwoFactor\Contracts\TwoFactorTotp;
use ParagonIE\ConstantTime\Base32;

use function array_merge;
use function config;
use function json_encode;
use function random_bytes;
use function strtolower;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property-read int $id
 * @property-read null|\Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable $authenticatable
 * @property string $shared_secret
 * @property string $label
 * @property int $digits
 * @property int $seconds
 * @property int $window
 * @property string $algorithm
 * @property array $totp_config
 * @property \Illuminate\Support\Collection<int, array{code: string, used_at: \Illuminate\Support\Carbon|null}>|null $recovery_codes
 * @property \Illuminate\Support\Collection<int, array{"2fa_remember": string, ip: string, added_at: integer}>|null $safe_devices
 * @property \Illuminate\Support\Carbon|\DateTimeInterface|null $enabled_at
 * @property \Illuminate\Support\Carbon|\DateTimeInterface|null $recovery_codes_generated_at
 * @property \Illuminate\Support\Carbon|\DateTimeInterface|null $updated_at
 * @property \Illuminate\Support\Carbon|\DateTimeInterface|null $created_at
 *
 * @method static \Database\Factories\Laragear\TwoFactor\TwoFactorAuthenticationFactory<static> factory($count = null, $state = [])
 */
class TwoFactorAuthentication extends Model implements TwoFactorTotp
{
    use Concerns\HandlesCodes;
    use Concerns\HandlesRecoveryCodes;
    use Concerns\HandlesSafeDevices;
    use Concerns\SerializesSharedSecret;
    use HasFactory;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shared_secret' => 'encrypted',
        'authenticatable_id' => 'int',
        'digits' => 'int',
        'seconds' => 'int',
        'window' => 'int',
        'recovery_codes' => 'encrypted:collection',
        'safe_devices' => 'collection',
        'enabled_at' => 'datetime',
        'recovery_codes_generated_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['digits', 'seconds', 'window', 'algorithm'];

    /**
     * The model that uses Two-Factor Authentication.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo('authenticatable');
    }

    /**
     * Sets the Algorithm to lowercase.
     */
    protected function setAlgorithmAttribute(string $value): void
    {
        $this->attributes['algorithm'] = strtolower($value);
    }

    /**
     * Returns if the Two-Factor Authentication has been enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }

    /**
     * Returns if the Two-Factor Authentication is not being enabled.
     */
    public function isDisabled(): bool
    {
        return ! $this->isEnabled();
    }

    /**
     * Flushes all authentication data and cycles the Shared Secret.
     *
     * @return $this
     */
    public function flushAuth(): static
    {
        $this->recovery_codes_generated_at = null;
        $this->safe_devices = null;
        $this->enabled_at = null;

        $this->attributes = array_merge($this->attributes, config('two-factor.totp'));

        $this->shared_secret = static::generateRandomSecret();
        $this->recovery_codes = null;

        return $this;
    }

    /**
     * Creates a new Random Secret.
     */
    public static function generateRandomSecret(): string
    {
        return Base32::encodeUpper(
            random_bytes(config('two-factor.secret_length'))
        );
    }

    /**
     * @inheritDoc
     */
    protected static function newFactory(): Factory
    {
        return new TwoFactorAuthenticationFactory();
    }

    /**
     * Convert the model instance to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toUri(), $options);
    }
}
