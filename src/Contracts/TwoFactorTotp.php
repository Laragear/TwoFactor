<?php

namespace Laragear\TwoFactor\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Support\Renderable;
use Stringable;

interface TwoFactorTotp extends Renderable, Stringable
{
    /**
     * Validates a given code, optionally for a given timestamp and future window.
     */
    public function validateCode(string $code, DateTimeInterface|int|string $at = 'now', ?int $window = null): bool;

    /**
     * Creates a Code for a given timestamp, optionally by a given period offset.
     */
    public function makeCode(DateTimeInterface|int|string $at = 'now', int $offset = 0): string;

    /**
     * Returns the Shared Secret as a QR Code.
     */
    public function toQr(): string;

    /**
     * Returns the Shared Secret as a string.
     */
    public function toString(): string;

    /**
     * Returns the Shared Secret as a URI.
     */
    public function toUri(): string;
}
