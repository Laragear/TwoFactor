<?php

namespace Laragear\TwoFactor;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\Exceptions\InvalidCodeException;
use function app;
use function trans;
use function validator;

class TwoFactor
{
    /**
     * Check if the user uses TOTP and has a valid code when login in.
     *
     * @return \Closure(\Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable):bool
     */
    public static function hasCode(string $input = '2fa_code'): Closure
    {
        return static function ($user) use ($input): bool {
            return app(__CLASS__, ['input' => $input])->validate($user);
        };
    }

    /**
     * Check if the user uses TOTP and has a valid code when login in.
     *
     * @return \Closure(\Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable):bool
     */
    public static function hasCodeOrFails(string $input = '2fa_code', string $message = null): Closure
    {
        return static function ($user) use ($input, $message): bool {
            return app(__CLASS__, ['input' => $input])->validate($user)
                ?: throw InvalidCodeException::withMessages([
                    $input => $message ?? trans('two-factor::validation.totp_code'),
                ]);
        };
    }

    /**
     * Creates a new Laraguard instance.
     */
    public function __construct(protected Repository $config, protected Request $request, protected string $input)
    {
        //
    }

    /**
     * Check if the user uses TOTP and has a valid code.
     *
     * If the user does not use TOTP, no checks will be done.
     */
    public function validate(Authenticatable $user): bool
    {
        // If the user does not use 2FA or is not enabled, don't check.
        if (! $user instanceof TwoFactorAuthenticatable || ! $user->hasTwoFactorEnabled()) {
            return true;
        }

        // If safe devices are enabled, and this is a safe device, bypass.
        if ($this->isSafeDevicesEnabled() && $user->isSafeDevice($this->request)) {
            return true;
        }

        // If the code is valid, return true after it tries to save the device.
        if ($this->requestHasCode() && $user->validateTwoFactorCode($this->getCode())) {
            if ($this->isSafeDevicesEnabled() && $this->wantsToAddDevice()) {
                $user->addSafeDevice($this->request);
            }

            return true;
        }

        return false;
    }

    /**
     * Checks if the app config has Safe Devices enabled.
     */
    protected function isSafeDevicesEnabled(): bool
    {
        return $this->config->get('two-factor.safe_devices.enabled', false);
    }

    /**
     * Checks if the Request has a Two-Factor Code and is valid.
     */
    protected function requestHasCode(): bool
    {
        return ! validator($this->request->only($this->input), [
            $this->input => 'required|alpha_num',
        ])->fails();
    }

    /**
     * Returns the code from the request input.
     */
    protected function getCode(): string
    {
        return $this->request->input($this->input);
    }

    /**
     * Checks if the user wants to add this device as "safe".
     */
    protected function wantsToAddDevice(): bool
    {
        return $this->request->filled('safe_device');
    }
}
