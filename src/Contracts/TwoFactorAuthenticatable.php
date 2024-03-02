<?php

namespace Laragear\TwoFactor\Contracts;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface TwoFactorAuthenticatable
{
    /**
     * Determines if the User has Two-Factor Authentication enabled or not.
     */
    public function hasTwoFactorEnabled(): bool;

    /**
     * Enables Two-Factor Authentication for the given user.
     */
    public function enableTwoFactorAuth(): void;

    /**
     * Disables Two-Factor Authentication for the given user.
     */
    public function disableTwoFactorAuth(): void;

    /**
     * Recreates the Two-Factor Authentication from the ground up, and returns a new Shared Secret.
     */
    public function createTwoFactorAuth(): TwoFactorTotp;

    /**
     * Confirms the Shared Secret and fully enables the Two-Factor Authentication.
     */
    public function confirmTwoFactorAuth(string $code): bool;

    /**
     * Validates the TOTP Code or Recovery Code.
     */
    public function validateTwoFactorCode(?string $code = null, bool $useRecoveryCodes = true): bool;

    /**
     * Makes a Two-Factor Code for a given time, and period offset.
     */
    public function makeTwoFactorCode(DateTimeInterface|int|string $at = 'now', int $offset = 0): string;

    /**
     * Return the current set of Recovery Codes.
     */
    public function getRecoveryCodes(): Collection;

    /**
     * Generates a new set of Recovery Codes.
     */
    public function generateRecoveryCodes(): Collection;

    /**
     * Return all the Safe Devices that bypass Two-Factor Authentication.
     */
    public function safeDevices(): Collection;

    /**
     * Adds a "safe" Device from the Request, and returns the token used to identify it.
     */
    public function addSafeDevice(Request $request): string;

    /**
     * Determines if the Request has been made through a previously used "safe" device.
     */
    public function isSafeDevice(Request $request): bool;

    /**
     * Sets a flag on the User to signal that a recent Two-Factor challenge has been bypassed under a Safe Device.
     */
    public function setTwoFactorBypassedBySafeDevice(bool $condition): void;

    /**
     * Determines if a recent Two-Factor challenge has been bypassed under a Safe Device.
     */
    public function wasTwoFactorBypassedBySafeDevice(): bool;
}
