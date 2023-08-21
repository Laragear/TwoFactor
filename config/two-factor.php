<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database table name
    |--------------------------------------------------------------------------
    |
    | If you have modified the default table name during your migration, then
    | set this to your actual table name. Otherwise, leave this as null to use
    | the default table name.
    |
    */

    'table_name' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Codes can only be used one time, so we will hold them in the cache for
    | the period it shouldn't be used again. You can customize the default
    | cache store to use. Using "null" will use the default cache store.
    |
    */

    'cache' => [
        'store'  => null,
        'prefix' => '2fa.code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Codes
    |--------------------------------------------------------------------------
    |
    | This option controls the recovery codes generation. By default is enabled
    | so users have a way to authenticate without a code generator. The length
    | of the codes, as their quantity, can be configured to tighten security.
    |
    */

    'recovery' => [
        'enabled' => true,
        'codes'   => 10,
        'length'  => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Devices
    |--------------------------------------------------------------------------
    |
    | Authenticating with Two-Factor Codes can become very obnoxious when the
    | user does it every time. "Safe devices" allows to remember the device
    | for a period of time which 2FA Codes won't be asked when login in.
    |
    */

    'safe_devices' => [
        'enabled'         => false,
        'cookie'          => '_2fa_remember',
        'max_devices'     => 3,
        'expiration_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Require Two-Factor Middleware
    |--------------------------------------------------------------------------
    |
    | The "2fa.confirm" middleware acts as a gatekeeper to a route by asking
    | the user to confirm with a 2FA Code. This configuration sets the key
    | of the session to remember the data and how much time to remember.
    |
    | Time is set in minutes.
    |
    */

    'confirm' => [
        'key' => '_2fa',
        'time' => 60 * 3, // 3 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Login Helper
    |--------------------------------------------------------------------------
    |
    | When using the Login Helper these defaults will be used to show the 2FA
    | form, and hold the encrypted login credentials in the session for only
    | the next request. If flash is "false" the input will be kept forever.
    |
    | You may set "flash" to "false" if you are using Livewire or Inertia,
    | because, these may make request before the 2FA Code is sent again,
    | removing the credentials and invalidating the whole login flow.
    |
    */

    'login' => [
        'view' => 'two-factor::login',
        'key' => '_2fa_login',
        'flash' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret Length
    |--------------------------------------------------------------------------
    |
    | The package uses a shared secret length of 160-bit, as recommended by the
    | RFC 4226. This makes it compatible with most 2FA apps. You can change it
    | freely but consider the standard allows shared secrets down to 128-bit.
    |
    */

    'secret_length' => 20,

    /*
    |--------------------------------------------------------------------------
    | TOTP config
    |--------------------------------------------------------------------------
    |
    | While this package uses recommended RFC 4226 and RDC 6238 settings, you
    | can further configure how TOTP should work. These settings are saved
    | for each 2FA authentication, so it will only affect new accounts.
    |
    */

    'issuer' => env('OTP_TOTP_ISSUER'),

    'totp' => [
        'digits'    => 6,
        'seconds'   => 30,
        'window'    => 1,
        'algorithm' => 'sha1',
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Config
    |--------------------------------------------------------------------------
    |
    | This package uses the BaconQrCode generator package to generate QR codes
    | as SVG. These size and image margin values are used to create them. You
    | can always your own code to create personalized QR Codes from the URI.
    |
    */

    'qr_code' => [
        'size'   => 400,
        'margin' => 4,
    ],
];
