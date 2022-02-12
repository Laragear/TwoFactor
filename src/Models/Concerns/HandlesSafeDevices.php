<?php

namespace Laragear\TwoFactor\Models\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait HandlesSafeDevices
{
    /**
     * Returns the timestamp of the Safe Device.
     *
     * @param  null|string  $token
     * @return null|\Illuminate\Support\Carbon
     */
    public function getSafeDeviceTimestamp(string $token = null): ?Carbon
    {
        if ($token && $device = $this->safe_devices?->firstWhere('2fa_remember', $token)) {
            return Carbon::createFromTimestamp($device['added_at']);
        }

        return null;
    }

    /**
     * Generates a Device token to bypass Two-Factor Authentication.
     *
     * @return string
     */
    public static function generateDefaultTwoFactorRemember(): string
    {
        return Str::random(100);
    }
}
