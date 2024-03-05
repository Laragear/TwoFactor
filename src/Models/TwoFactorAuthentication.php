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
 * @property null|\Illuminate\Support\Collection $recovery_codes
 * @property null|\Illuminate\Support\Collection $safe_devices
 * @property null|\Illuminate\Support\Carbon|\DateTime $enabled_at
 * @property null|\Illuminate\Support\Carbon|\DateTime $recovery_codes_generated_at
 * @property null|\Illuminate\Support\Carbon|\DateTime $updated_at
 * @property null|\Illuminate\Support\Carbon|\DateTime $created_at
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
     * The default table name to use.
     *
     * @var string
     */
    public const DEFAULT_TABLE_NAME = 'two_factor_authentications';

    /**
     * The table name to use.
     *
     * @var string
     */
    public static string $useTable = self::DEFAULT_TABLE_NAME;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shared_secret' => 'encrypted',
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
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        if (!$this->table) {
            $this->table = static::$useTable;
        }

        return parent::getTable();
    }

    /**
     * Sets the Algorithm to lowercase.
     *
     * @param  $value
     * @return void
     */
    protected function setAlgorithmAttribute($value): void
    {
        $this->attributes['algorithm'] = strtolower($value);
    }

    /**
     * Returns if the Two-Factor Authentication has been enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }

    /**
     * Returns if the Two-Factor Authentication is not been enabled.
     *
     * @return bool
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
     *
     * @return string
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
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toUri(), $options);
    }
}
