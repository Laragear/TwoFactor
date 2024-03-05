<?php

namespace Laragear\TwoFactor\Models\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function is_int;
use function now;
use function strtoupper;

trait HandlesRecoveryCodes
{
    /**
     * The custom generator to make recovery codes.
     *
     * @var (callable(int, int, int): \Illuminate\Support\Collection<int, int|string>)|null
     */
    protected static $generator = null;

    /**
     * Returns if there are Recovery Codes available.
     *
     * @return bool
     */
    public function containsUnusedRecoveryCodes(): bool
    {
        return (bool) $this->recovery_codes?->contains('used_at', '==', null);
    }

    /**
     * Returns the key of the not-used Recovery Code.
     *
     * @param  string  $code
     * @return int|bool|null
     */
    protected function getUnusedRecoveryCodeIndex(string $code): int|null|bool
    {
        return $this->recovery_codes?->search([
            'code' => $code,
            'used_at' => null,
        ], true);
    }

    /**
     * Sets a Recovery Code as used.
     *
     * @param  string  $code
     * @return bool
     */
    public function setRecoveryCodeAsUsed(string $code): bool
    {
        $index = $this->getUnusedRecoveryCodeIndex($code);

        if (! is_int($index)) {
            return false;
        }

        $this->recovery_codes = $this->recovery_codes->put($index, [
            'code' => $code,
            'used_at' => now(),
        ]);

        return true;
    }

    /**
     * Registers a callback to generate recovery codes.
     *
     * @param  (callable(int $length, int $iteration, int $amount): \Illuminate\Support\Collection<int, int|string>)|null  $callback
     * @return void
     */
    public static function generateRecoveryCodesUsing(callable $callback = null): void
    {
        static::$generator = $callback;
    }

    /**
     * Generates a new batch of Recovery Codes.
     *
     * @param  int  $amount
     * @param  int  $length
     * @return \Illuminate\Support\Collection
     */
    public static function generateRecoveryCodes(int $amount, int $length): Collection
    {
        $generator = static::$generator ?? static function ($length): string {
            return strtoupper(Str::random($length));
        };

        return Collection::times($amount, static function (int $iteration) use ($generator, $amount, $length): array {
            return [
                'code' => $generator($length, $iteration, $amount),
                'used_at' => null,
            ];
        });
    }
}
