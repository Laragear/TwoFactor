# Two Factor
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/two-factor.svg)](https://packagist.org/packages/laragear/two-factor)
[![Latest stable test run](https://github.com/Laragear/TwoFactor/workflows/Tests/badge.svg)](https://github.com/Laragear/TwoFactor/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/TwoFactor/branch/1.x/graph/badge.svg?token=BJMBVZNPM8)](https://codecov.io/gh/Laragear/TwoFactor)
[![Maintainability](https://api.codeclimate.com/v1/badges/64241e25adb0f55d7ba1/maintainability)](https://codeclimate.com/github/Laragear/TwoFactor/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_TwoFactor&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_TwoFactor)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/10.x/octane#introduction)

On-premises Two-Factor Authentication for all your users out of the box.

```php
use Illuminate\Http\Request;
use Laragear\TwoFactor\Facades\Auth2FA;

public function login(Request $request)
{
    $attempt = Auth2FA::attempt($request->only('email', 'password'));
    
    if ($attempt) {
        return 'You are logged in!';
    }
    
    return 'Hey, you should make an account!';
}
```

This package enables TOTP authentication using 6 digits codes. No need for external APIs.

> Want to authenticate users with fingerprints, patterns or biometric data? Check out [Laragear WebAuthn](https://github.com/Laragear/WebAuthn).

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FTwoFactor&hashtags=PHP,Laravel)**

## Requirements

* PHP 8 or later
* Laravel 9, 10 or later

## Installation

Fire up Composer and require this package in your project.

    composer require laragear/two-factor

That's it.

### How this works

This package adds a **Contract** to detect if, after the credentials are deemed valid, should use Two-Factor Authentication as a second layer of authentication.

It includes a custom **view** and a **callback** to handle the Two-Factor authentication itself during login attempts.

Works without middleware or new guards, but you can go full manual if you want.

## Set up

1. First, publish the migration, translations, views and config into your application, and use `migrate` to create the table that handles the Two-Factor Authentication information for each model you want to attach to 2FA.

```shell
php artisan vendor:publish --provider="Laragear\TwoFactor\TwoFactorServiceProvider"
php artisan migrate
```

Alternatively, you can use `--tag="migrations"` to only publish the migration files.

> [!TIP]
>
> Remember that you can edit the migration by adding new columns before migrating, and also the [table name]()

2. Add the `TwoFactorAuthenticatable` _contract_ and the `TwoFactorAuthentication` trait to the User model, or any other model you want to make Two-Factor Authentication available. 

```php
<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use TwoFactorAuthentication;
    
    // ...
}
```

> The contract is used to identify the model using Two-Factor Authentication, while the trait conveniently implements the methods required to handle it.

That's it. You're now ready to use 2FA in your application.

### Enabling Two-Factor Authentication

To enable Two-Factor Authentication for the User, he must sync the Shared Secret between its Authenticator app and the application. 

> Some free Authenticator Apps are [iOS Authenticator](https://www.apple.com/ios/ios-15-preview/features), [FreeOTP](https://freeotp.github.io/), [Authy](https://authy.com/), [andOTP](https://github.com/andOTP/andOTP), [Google](https://apps.apple.com/app/google-authenticator/id388497605) [Authenticator](https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en), and [Microsoft Authenticator](https://www.microsoft.com/en-us/account/authenticator), to name a few.

To start, generate the needed data using the `createTwoFactorAuth()` method. This returns a serializable _Shared Secret_ that you can show to the User as a string or QR Code (encoded as SVG) in your view.

```php
use Illuminate\Http\Request;

public function prepareTwoFactor(Request $request)
{
    $secret = $request->user()->createTwoFactorAuth();
    
    return view('user.2fa', [
        'qr_code' => $secret->toQr(),     // As QR Code
        'uri'     => $secret->toUri(),    // As "otpauth://" URI.
        'string'  => $secret->toString(), // As a string
    ]);
}
```

> When you use `createTwoFactorAuth()` on someone with Two-Factor Authentication already enabled, the previous data becomes permanently invalid. This ensures a User **never** has two Shared Secrets enabled at any given time.

Then, the User must confirm the Shared Secret with a Code generated by their Authenticator app. The `confirmTwoFactorAuth()` method will automatically enable it if the code is valid.

```php
use Illuminate\Http\Request;

public function confirmTwoFactor(Request $request)
{
    $request->validate([
        'code' => 'required|numeric'
    ]);
    
    $activated = $request->user()->confirmTwoFactorAuth($request->code);
    
    // ...
}
```

If the User doesn't issue the correct Code, the method will return `false`. You can tell the User to double-check its device's timezone, or create another Shared Secret with `createTwoFactorAuth()`.

### Recovery Codes

Recovery Codes are automatically generated each time the Two-Factor Authentication is enabled. By default, a Collection of ten one-use 8-characters codes are created.

You can show them using `getRecoveryCodes()`.

```php
use Illuminate\Http\Request;

public function confirmTwoFactor(Request $request)
{
    if ($request->user()->confirmTwoFactorAuth($request->code)) {
        return $request->user()->getRecoveryCodes();
    }
    
    return 'Try again!';
}
```

You're free on how to show these codes to the User, but **ensure** you show them at least one time after a successfully enabling Two-Factor Authentication, and ask him to print them somewhere.

> These Recovery Codes are handled automatically when the User sends it instead of a TOTP code. If it's a recovery code, the package will use and mark it as invalid, so it can't be used again.

The User can generate a fresh batch of codes using `generateRecoveryCodes()`, which replaces the previous batch.

```php
use Illuminate\Http\Request;

public function showRecoveryCodes(Request $request)
{
    return $request->user()->generateRecoveryCodes();
}
```

> If the User depletes his recovery codes without disabling Two-Factor Authentication, or Recovery Codes are deactivated, **he may be locked out forever without his Authenticator app**. Ensure you have countermeasures in these cases.

#### Custom Recovery Codes

While it's not recommended, as the included logic will suffice for the vast majority of situations, you can create your own generator for recovery codes. Just add a callback using the  `generateRecoveryCodesUsing()` of the `TwoFactorAuthentication` model.

This method receives a callback that should return a random alphanumeric code, and will be invoked on each code to generate.

```php
use Laragear\TwoFactor\Models\TwoFactorAuthentication;
use MyRandomGenerator;

$generator = function ($length, $iteration, $amount) {
    return MyRandomGenerator::random($length)->make();
}

TwoFactorAuthentication::generateRecoveryCodesUsing($generator);
```

### Logging in

The easiest way to login users in your application is to use the `Auth2FA` facade. It comes with everything you would need to handle a user that requires a 2FA Code:

- Only works if the user has 2FA enabled.
- Shows a custom form if the 2FA code is required.
- Credentials are encrypted and flashed in the session to re-use them. 

In your Login Controller, use the `Auth2FA::attempt()` method with the credentials. If the user requires a 2FA Code, it will automatically stop the authentication and show a form to use it.

You can **blatantly copy-and-paste this code** in your log in controller:

```php
use Laragear\TwoFactor\Facades\Auth2FA;
use Illuminate\Http\Request;

public function login(Request $request)
{
    // If the user is trying for the first time, ensure both email and the password are
    // required to log in. If it's not, then he would issue its 2FA code. This ensures
    // the credentials are not required again when is just issuing his 2FA code alone.
    if ($request->isNotFilled('2fa_code')) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);
    }
    
    $attempt = Auth2FA::attempt($request->only('email', 'password'), $request->filled('remember'));
    
    if ($attempt) {
        return redirect()->home();
    }
    
    return back()->withErrors(['email' => 'There is no existing user for these credentials']);
}
```

You can further customize how to handle the 2FA code authentication procedure with the following fluent methods:

| Method            | Description                                                                       |
|-------------------|-----------------------------------------------------------------------------------|
| guard($guard)     | The guard to use for authentication. Defaults to the application default (`web`). |
| view($view)       | Return a custom view to handle the 2FA Code retry.                                |
| redirect($route)  | Redirect to a location to handle the 2FA Code retry.                              |
| message($message) | Return a custom message when the 2FA code fails or is not present.                |
| input($input)     | Sets the input where the TOTP code is in the request. Defaults to `2fa_code`.     |
| sessionKey($key)  | The key used to flash the encrypted credentials. Defaults to `_2fa_login`.        |

> * For [Laravel UI](https://github.com/laravel/ui), override the `attemptLogin()` method to replace the default guard attempt with `Auth2FA::attempt()` and `validateLogin` method to wrap in the `if ($request->isNotFilled('2fa_code'))` statement in your Login controller.
> * For [Laravel Breeze](https://laravel.com/docs/starter-kits#laravel-breeze), you may need to extend the `LoginRequest::authenticate()` call.
> * For [Laravel Fortify](https://laravel.com/docs/fortify) and [Jetstream](https://jetstream.laravel.com/), you may need to set a custom callback with the [`Fortify::authenticateUsing()`](https://laravel.com/docs/10.x/fortify#customizing-user-authentication) method.

Alternatively, you may use `Auth::attemptWhen()` with TwoFactor helper methods, which returns a callback to check if the user needs a 2FA Code before proceeding using `TwoFactor::hasCode()`.

```php
use Illuminate\Support\Facades\Auth;
use Laragear\TwoFactor\TwoFactor;

$attempt = Auth::attemptWhen(
    [/* Credentials... */], TwoFactor::hasCode(), $request->filled('remember')
);
```

You can use the `hasCodeOrFails()` method that does the same, but throws a validation exception, which is handled gracefully by the framework. It even accepts a custom message in case of failure, otherwise a default [translation](#translations) line will be used.

### Deactivation

You can deactivate Two-Factor Authentication for a given User using the `disableTwoFactorAuth()` method. This will automatically invalidate the authentication data, allowing the User to log in with just his credentials.

```php
public function disableTwoFactorAuth(Request $request)
{
    $request->user()->disableTwoFactorAuth();
    
    return 'Two-Factor Authentication has been disabled!';
}
```

## Events

The following events are fired in addition to the default Authentication events.

* `TwoFactorEnabled`: An User has enabled Two-Factor Authentication.
* `TwoFactorRecoveryCodesDepleted`: An User has used his last Recovery Code.
* `TwoFactorRecoveryCodesGenerated`: An User has generated a new set of Recovery Codes.
* `TwoFactorDisabled`: An User has disabled Two-Factor Authentication.

> You can use `TwoFactorRecoveryCodesDepleted` to tell the User to create more Recovery Codes or mail them some more.

## Middleware

TwoFactor comes with two middleware for your routes: `2fa.enabled` and `2fa.confirm`.

> To avoid unexpected results, middleware only act on your users models implementing the `TwoFactorAuthenticatable` contract. If a user model doesn't implement it, the middleware will bypass any 2FA logic.

### Require 2FA

If you need to ensure the User has Two-Factor Authentication enabled before entering a given route, you can use the `2fa.enabled` middleware. Users who implement the `TwoFactorAuthenticatable` contract and have 2FA disabled will be redirected to a route name containing the warning, which is `2fa.notice` by default.

```php
Route::get('system/settings', function () {
    // ...
})->middleware('2fa.enabled');
```

You can implement the view easily with the one included in this package, optionally with a URL to point the user to enable 2FA:

```php
use Illuminate\Support\Facades\Route;

Route::view('2fa-required', 'two-factor::notice', [
    'url' => url('settings/2fa')
])->name('2fa.notice');
```

### Confirm 2FA

Much like the [`password.confirm` middleware](https://laravel.com/docs/authentication#password-confirmation), you can also ask the user to confirm entering a route by issuing a 2FA Code with the `2fa.confirm` middleware.

```php
Route::get('api/token', function () {
    // ...
})->middleware('2fa.confirm');

Route::post('api/token/delete', function () {
    // ...
})->middleware('2fa.confirm');
```

The middleware will redirect the user to the named route `2fa.confirm` by default, but you can change it in the first parameter. To implement the receiving routes, TwoFactor comes with the `Confirm2FACodeController` and a view you can use for a quick start.

```php
use Illuminate\Support\Facades\Route;
use Laragear\TwoFactor\Http\Controllers\ConfirmTwoFactorCodeController;

Route::get('2fa-confirm', [ConfirmTwoFactorCodeController::class, 'form'])
    ->name('2fa.confirm');

Route::post('2fa-confirm', [ConfirmTwoFactorCodeController::class, 'confirm']);
```

Since a user without 2FA enabled won't be asked for a code, you can combine the middleware with `2fa.require` to ensure confirming is mandatory for users without 2FA enabled.

```php
use Illuminate\Support\Facades\Route;

Route::get('api/token', function () {
    // ...
})->middleware('2fa.require', '2fa.confirm');
```

## Validation

Sometimes you may want to manually trigger a TOTP validation in any part of your application for the authenticated user. You can validate a TOTP code for the authenticated user using the `topt` rule.

```php
public function checkTotp(Request $request)
{
    $request->validate([
        'code' => 'totp'
    ]);

    // ...
}
```

This rule will succeed only if  the user is authenticated, it has Two-Factor Authentication enabled, and the code is correct or is a recovery code.

> You can enforce the rule to NOT use recovery codes using `totp:code`.

## Translations

TwoFactor comes with translation files that you can use immediately in your application. These are also used for the [validation rule](#validation).

```php
public function disableTwoFactorAuth()
{
    // ...

    session()->flash('message', trans('two-factor::messages.success'));

    return back();
}
```

To add your own language, publish the translation files. These will be located in `lang/vendor/two-factor`:

```shell
php artisan vendor:publish --provider="Laragear\TwoFactor\TwoFactorServiceProvider" --tag="translations"
```

## Configuration

To further configure the package, publish the configuration file:

```shell
php artisan vendor:publish --provider="Laragear\TwoFactor\TwoFactorServiceProvider" --tag="config"
```

You will receive the `config/two-factor.php` config file with the following contents:

```php
return [
    'cache' => [
        'store' => null,
        'prefix' => '2fa.code'
    ],
    'recovery' => [
        'enabled' => true,
        'codes' => 10,
        'length' => 8,
	],
    'safe_devices' => [
        'enabled' => false,
        'max_devices' => 3,
        'expiration_days' => 14,
	],
    'confirm' => [
        'key' => '_2fa',
        'time' => 60 * 3,
    ],
    'login' => [
        'view' => 'two-factor::login',
        'key' => '_2fa_login',
        'flash' => true,
    ],
    'secret_length' => 20,
    'issuer' => env('OTP_TOTP_ISSUER'),
    'totp' => [
        'digits' => 6,
        'seconds' => 30,
        'window' => 1,
        'algorithm' => 'sha1',
    ],
    'qr_code' => [
        'size' => 400,
        'margin' => 4
    ],
];
```

### Cache Store

```php
return  [
    'cache' => [
        'store' => null,
        'prefix' => '2fa.code'
    ],
];
```

[RFC 6238](https://tools.ietf.org/html/rfc6238#section-5) states that one-time passwords shouldn't be able to be usable more than once, even if is still inside the time window. For this, we need to use the Cache to ensure the same code cannot be used again.

You can change the store to use, which it's the default used by your application, and the prefix to use as cache keys, in case of collisions.

### Recovery

```php
return [
    'recovery' => [
        'enabled' => true,
        'codes' => 10,
        'length' => 8,
    ],
];
```

Recovery codes handling are enabled by default, but you can disable it. If you do, ensure Users can authenticate by other means, like sending an email with a link to a signed URL that logs him in and disables Two-Factor Authentication, or SMS.

The number and length of codes generated is configurable. 10 Codes of 8 random characters are enough for most authentication scenarios.

### Safe devices

```php
return [
    'safe_devices' => [
        'enabled' => false,
        'max_devices' => 3,
        'expiration_days' => 14,
    ],
];
```

Enabling this option will allow the application to "remember" a device using a cookie, allowing it to bypass Two-Factor Authentication once a code is verified in that device. When the User logs in again in that device, it won't be prompted for a 2FA Code again.

The cookie contains a random value which is checked against a list of safe devices saved for the authenticating user. It's considered a safe device if the value matches and has not expired.

There is a limit of devices that can be saved, but usually three is enough (phone, tablet and PC). New devices will displace the oldest devices registered. Devices are considered no longer "safe" until a set amount of days.

You can change the maximum number of devices saved and the amount of days of validity once they're registered. More devices and more expiration days will make the Two-Factor Authentication less secure.

> When disabling Two-Factor Authentication, the list of devices is flushed.

### Confirmation Middleware

```php
return [
    'confirm' => [
        'key' => '_2fa',
        'time' => 60 * 3,
    ],
];
```

These control which key to use in the session for handling [`2fa.confirm` middleware](#confirm-2fa), and the expiration time in minutes.

### Login Helper

```php
return [
    'login' => [
        'view' => 'two-factor::login',
        'key' => '_2fa_login',
        'flash' => true,
    ],
];
```

This controls the login helper configuration, like the Blade view to render, the session key to hold the login input (like email and password), and if it should store these credentials using `flash` or just `put`.

About the use of `flash`, you may disable it if you expect other requests during login, like it may happen with Inertia.js or Livewire, but this may keep the login input forever in the session, which in some cases it may be undesirable.

### Secret length

```php
return [
    'secret_length' => 20,
];
```

This controls the length (in bytes) used to create the Shared Secret. While a 160-bit shared secret is enough, you can tighten or loosen the secret length to your liking.

It's recommended to use 128-bit or 160-bit because some Authenticator apps may have problems with non-RFC-recommended lengths.

### TOTP Configuration 

```php
return [
    'issuer' => env('OTP_TOTP_ISSUER'),
    'totp' => [
        'digits' => 6,
        'seconds' => 30,
        'window' => 1,
        'algorithm' => 'sha1',
    ],
];
```

This controls TOTP code generation and verification mechanisms:

* Issuer: The name of the issuer of the TOTP. Default is the application name. 
* TOTP Digits: The amount of digits to ask for TOTP code. 
* TOTP Seconds: The number of seconds a code is considered valid.
* TOTP Window: Additional steps of seconds to keep a code as valid.
* TOTP Algorithm: The system-supported algorithm to handle code generation.

This configuration values are always URL-encoded and passed down to the authentication app as URI parameters:

    otpauth://totp/Laravel%30taylor%40laravel.com?secret=THISISMYSECRETPLEASEDONOTSHAREIT&issuer=Laravel&label=Laravel%30taylor%40laravel.com&algorithm=SHA1&digits=6&period=30

These values are printed to each 2FA data record inside the application. Changes will only take effect for new activations.

> Do not edit these parameters if you plan to use publicly available Authenticator apps, since some of them **may not support non-standard configuration**, like more digits, different period of seconds or other algorithms.

### QR Code Configuration 

```php
return [
    'qr_code' => [
        'size' => 400,
        'margin' => 4
    ],
];
```

This controls the size and margin used to create the QR Code, which are created as SVG.

## Custom TOTP Label

You may change how your model creates a TOTP Label, which is shown to the user on its authenticator, using the `getTwoFactorIssuer()` and `getTwoFactorUserIdentifier()` methods of your user.

For example, we can change the issuer and identifier depending on which domain the user is visiting.

```php
public function getTwoFactorIssuer(): string
{
    return request()->getHost();
}

public function getTwoFactorUserIdentifier(): string
{
    return request()->getHost() === 'admin.myapp.com'
        ? $this->getAttribute('name')
        : $this->getAttribute('email');
}
```

The above will render `users.myapp.com:john@gmail.com` or `admin.myapp.com:John Doe`.

## Custom table name

By default, the `TwoFactorAuthentication` model will use the `two_factor_authentications` table. If you want to change the table name for whatever reason, set the table using the `$useTable` static property. You should do this on the `boot()` method of your `AppServiceProvider`.

```php
use Laragear\TwoFactor\Models\TwoFactorAuthentication;

public function boot(): void
{
    TwoFactorAuthentication::$useTable = 'my_custom_table';
}
```

After that, you may migrate your table like always through the Artisan command. The migration will automatically pick up the table name change.

```shell
php artisan migrate
```

## Laravel Octane Compatibility

- There are no singletons using a stale application instance. 
- There are no singletons using a stale config instance. 
- There are no singletons using a stale request instance. 
- There are no static properties written during a request.

There should be no problems using this package with Laravel Octane.

## Security

When using the [Login Helper](#logging-in), credentials are saved encrypted into the session. This can be undesirable for some applications. While this tool exists for convenience, you are welcome to create your own 2FA authentication flow.

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2023 Laravel LLC.
