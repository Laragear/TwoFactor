<?php

namespace Laragear\TwoFactor;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use function collect;
use function config;
use function cookie;
use function event;
use function now;

/**
 * @property-read \Laragear\TwoFactor\Models\TwoFactorAuthentication $twoFactorAuth
 */
trait TwoFactorAuthentication
{
    /**
     * Initialize the current Trait.
     */
    public function initializeTwoFactorAuthentication(): void
    {
        // For security, we will hide the Two-Factor Authentication data from the parent model.
        $this->makeHidden('twoFactorAuth');
    }

    /**
     * This connects the current Model to the Two-Factor Authentication model.
     */
    public function twoFactorAuth(): MorphOne
    {
        return $this->morphOne(Models\TwoFactorAuthentication::class, 'authenticatable')
            ->withDefault(static function (Models\TwoFactorAuthentication $model): Models\TwoFactorAuthentication {
                return $model->fill(config('two-factor.totp'));
            });
    }

    /**
     * Determines if the User has Two-Factor Authentication enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorAuth->isEnabled();
    }

    /**
     * Enables Two-Factor Authentication for the given user.
     */
    public function enableTwoFactorAuth(): void
    {
        $this->twoFactorAuth->enabled_at = now();

        if (config('two-factor.recovery.enabled')) {
            $this->generateRecoveryCodes();
        }

        $this->twoFactorAuth->save();

        event(new Events\TwoFactorEnabled($this));
    }

    /**
     * Disables Two-Factor Authentication for the given user.
     */
    public function disableTwoFactorAuth(): void
    {
        $this->twoFactorAuth->flushAuth()->delete();

        event(new Events\TwoFactorDisabled($this));
    }

    /**
     * Creates a new Two-Factor Auth mechanisms from scratch, and returns a new Shared Secret.
     */
    public function createTwoFactorAuth(): Contracts\TwoFactorTotp
    {
        $this->twoFactorAuth->flushAuth()->forceFill([
            'label' => $this->twoFactorLabel(),
        ])->save();

        return $this->twoFactorAuth;
    }

    /**
     * Returns the label for TOTP URI.
     */
    protected function twoFactorLabel(): string
    {
        // If the developer has set acustom label for the app, use that. When not,
        // we will fallback to the issuer name. We will use that string to append
        // it to the user email so the authenticator shows the TOTP origin name.
        $issuer = config('two-factor.label') ?? config('two-factor.issuer');

        return $issuer.':'.$this->getAttribute('email');
    }

    /**
     * Confirms the Shared Secret and fully enables the Two-Factor Authentication.
     */
    public function confirmTwoFactorAuth(string $code): bool
    {
        // If the Two-Factor is already enabled, there is no need to re-confirm the code.
        if ($this->hasTwoFactorEnabled()) {
            return true;
        }

        if ($this->validateCode($code)) {
            $this->enableTwoFactorAuth();

            return true;
        }

        return false;
    }

    /**
     * Verifies the Code against the Shared Secret.
     */
    protected function validateCode(string|int $code): bool
    {
        return $this->twoFactorAuth->validateCode($code);
    }

    /**
     * Validates the TOTP Code or Recovery Code.
     */
    public function validateTwoFactorCode(?string $code = null, bool $useRecoveryCodes = true): bool
    {
        return null !== $code
            && $this->hasTwoFactorEnabled()
            && ($this->validateCode($code) || ($useRecoveryCodes && $this->useRecoveryCode($code)));
    }

    /**
     * Makes a Two-Factor Code for a given time, and period offset.
     */
    public function makeTwoFactorCode(DateTimeInterface|int|string $at = 'now', int $offset = 0): string
    {
        return $this->twoFactorAuth->makeCode($at, $offset);
    }

    /**
     * Determines if the User has Recovery Codes available.
     */
    protected function hasRecoveryCodes(): bool
    {
        return $this->twoFactorAuth->containsUnusedRecoveryCodes();
    }

    /**
     * Return the current set of Recovery Codes.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRecoveryCodes(): Collection
    {
        return $this->twoFactorAuth->recovery_codes ?? collect();
    }

    /**
     * Generates a new set of Recovery Codes.
     *
     * @return \Illuminate\Support\Collection<int, array{code: string, used_at: \Illuminate\Support\Carbon}
     */
    public function generateRecoveryCodes(): Collection
    {
        [
            'two-factor.recovery.codes' => $amount,
            'two-factor.recovery.length' => $length
        ] = config()->get([
            'two-factor.recovery.codes', 'two-factor.recovery.length',
        ]);

        $this->twoFactorAuth->recovery_codes = Models\TwoFactorAuthentication::generateRecoveryCodes($amount, $length);
        $this->twoFactorAuth->recovery_codes_generated_at = now();
        $this->twoFactorAuth->save();

        event(new Events\TwoFactorRecoveryCodesGenerated($this));

        return $this->twoFactorAuth->recovery_codes;
    }

    /**
     * Uses a one-time Recovery Code if there is one available.
     */
    protected function useRecoveryCode(string $code): bool
    {
        if (! $this->twoFactorAuth->setRecoveryCodeAsUsed($code)) {
            return false;
        }

        $this->twoFactorAuth->save();

        if (! $this->hasRecoveryCodes()) {
            event(new Events\TwoFactorRecoveryCodesDepleted($this));
        }

        return true;
    }

    /**
     * Adds a "safe" Device from the Request, and returns the token used.
     */
    public function addSafeDevice(Request $request): string
    {
        [$name, $expiration] = array_values(config()->get([
            'two-factor.safe_devices.cookie', 'two-factor.safe_devices.expiration_days',
        ]));

        $this->twoFactorAuth->safe_devices = $this->safeDevices()
            ->push([
                '2fa_remember' => $token = $this->generateTwoFactorRemember(),
                'ip' => $request->ip(),
                'added_at' => $this->freshTimestamp()->getTimestamp(),
            ])
            ->sortByDesc('added_at') // Ensure the last is the first, so we can slice it.
            ->slice(0, config('two-factor.safe_devices.max_devices', 3))
            ->values();

        $this->twoFactorAuth->save();

        cookie()->queue($name, $token, $expiration * 1440);

        return $token;
    }

    /**
     * Generates a Device token to bypass Two-Factor Authentication.
     *
     * @return string
     */
    protected function generateTwoFactorRemember(): string
    {
        return Models\TwoFactorAuthentication::generateDefaultTwoFactorRemember();
    }

    /**
     * Deletes all saved safe devices.
     *
     * @return bool
     */
    public function flushSafeDevices(): bool
    {
        return $this->twoFactorAuth->setAttribute('safe_devices', null)->save();
    }

    /**
     * Return all the Safe Devices that bypass Two-Factor Authentication.
     *
     * @return \Illuminate\Support\Collection
     */
    public function safeDevices(): Collection
    {
        return $this->twoFactorAuth->safe_devices ?? collect();
    }

    /**
     * Determines if the Request has been made through a previously used "safe" device.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function isSafeDevice(Request $request): bool
    {
        $timestamp = $this->twoFactorAuth->getSafeDeviceTimestamp($this->getTwoFactorRememberFromRequest($request));

        if ($timestamp) {
            return $timestamp->addDays(config('two-factor.safe_devices.expiration_days'))->isFuture();
        }

        return false;
    }

    /**
     * Returns the Two-Factor Remember Token of the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function getTwoFactorRememberFromRequest(Request $request): ?string
    {
        return $request->cookie(config('two-factor.safe_devices.cookie', '2fa_remember'));
    }

    /**
     * Determines if the Request has been made through a not-previously-known device.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function isNotSafeDevice(Request $request): bool
    {
        return ! $this->isSafeDevice($request);
    }
}
