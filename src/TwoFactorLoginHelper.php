<?php

namespace Laragear\TwoFactor;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Laragear\TwoFactor\Exceptions\InvalidCodeException;
use function array_merge;
use function redirect;
use function response;
use function view;

class TwoFactorLoginHelper
{
    /**
     * The Authentication Guard to use, if any.
     *
     * @var string|null
     */
    protected ?string $guard = null;

    /**
     * Optional message to send as error.
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Create a new Two-Factor Login Helper instance.
     *
     * @param  \Illuminate\Auth\AuthManager  $auth
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $view
     * @param  string  $sessionKey
     * @param  bool  $useFlash
     * @param  string  $input
     */
    public function __construct(
        protected AuthManager $auth,
        protected Session $session,
        protected Request $request,
        protected string $view,
        protected string $sessionKey,
        protected bool $useFlash,
        protected string $input = '2fa_code',
        protected string $redirect = '',
    ) {
        //
    }

    /**
     * Return a custom view to handle the 2FA Code retry.
     *
     * @param  string  $view
     * @return $this
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Return a custom view to handle the 2FA Code retry.
     *
     * @param  string  $message
     * @return $this
     */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Sets the input where the TOTP code is in the credentials array. Defaults to `2fa_code`.
     *
     * @param  string  $input
     * @return $this
     */
    public function input(string $input): static
    {
        $this->input = $input;

        return $this;
    }

    /**
     *  The key used to flash the encrypted credentials.
     *
     * @param  string  $sessionKey
     * @return $this
     */
    public function sessionKey(string $sessionKey): static
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    /**
     * Set the guard to use for authentication.
     *
     * @param  string  $guard
     * @return $this
     */
    public function guard(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * Set the route to redirect the user on failed authentication.
     *
     * @param  string  $route
     * @return $this
     */
    public function redirect(string $route): static
    {
        $this->redirect = $route;

        return $this;
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * If the user receives
     *
     * @param  array  $credentials
     * @param  bool  $remember
     * @return bool
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        $guard = $this->getSessionGuard();

        // Always try to get the existing credentials, and merge them with the new.
        [$credentials, $remember] = $this->getFlashedData($credentials, $remember);

        // Try to authenticate the user with the credentials. If these are wrong
        // it will return false but, if the credentials are valid, we can catch
        // a custom exception to know if the 2FA Code was the one that failed.
        try {
            return $guard->attemptWhen(
                $credentials, TwoFactor::hasCodeOrFails($this->input, $this->message), $remember
            );
        } catch (InvalidCodeException $e) {
            $this->flashData($credentials, $remember);

            $this->throwResponse($this->input, $this->request->has($this->input) ? $e->errors() : []);
        }

        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Return the Session Guard of Laravel.
     *
     * @return \Illuminate\Auth\SessionGuard
     */
    protected function getSessionGuard(): SessionGuard
    {
        $guard = $this->auth->guard($this->guard);

        if (! $guard instanceof SessionGuard) {
            throw new InvalidArgumentException('The authentication guard must be a instance of SessionGuard.');
        }

        return $guard;
    }

    /**
     * Retrieve the flashed credentials in the session, and merges with the new on top.
     *
     * @param  array{credentials:array, remember:bool}  $credentials
     * @param  mixed  $remember
     * @return array
     */
    protected function getFlashedData(array $credentials, mixed $remember): array
    {
        $original = $this->session->pull("$this->sessionKey.credentials", []);
        $remember = $this->session->pull("$this->sessionKey.remember", $remember);

        // If the session is not encrypted, we will need to decrypt the credentials manually.
        if (! $this->session instanceof EncryptedStore) {
            foreach ($original as $index => $value) {
                $original[$index] = Crypt::decryptString($value);
            }
        }

        return [array_merge($original, $credentials), $remember];
    }

    /**
     * Flashes the credentials into the session, encrypted.
     *
     * @param  array  $credentials
     * @param  bool  $remember
     * @return void
     */
    protected function flashData(array $credentials, bool $remember): void
    {
        // Don't encrypt the credentials twice.
        if (! $this->session instanceof EncryptedStore) {
            foreach ($credentials as $key => $value) {
                $credentials[$key] = Crypt::encryptString($value);
            }
        }

        // If the developer has set the login helper to use flash, we will use that.
        // It may disable this, which in turn will use put. This wil fix some apps
        // like Livewire or Inertia, but it may keep this request input forever.
        if ($this->useFlash) {
            // @phpstan-ignore-next-line
            $this->session->flash($this->sessionKey, ['credentials' => $credentials, 'remember' => $remember]);
        } else {
            $this->session->put($this->sessionKey, ['credentials' => $credentials, 'remember' => $remember]);
        }
    }

    /**
     * Throw a response with an invalid TOTP code.
     *
     * @param  string  $input
     * @param  array  $errors
     * @return void
     */
    protected function throwResponse(string $input, array $errors): void
    {
        $response = $this->redirect
            ? redirect($this->redirect)->withInput(['input' => $input])->withErrors($errors)
            // @phpstan-ignore-next-line
            : response(view($this->view, ['input' => $input])->withErrors($errors));

        $response->throwResponse();
    }
}
