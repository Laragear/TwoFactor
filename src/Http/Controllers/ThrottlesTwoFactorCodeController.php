<?php

namespace Laragear\TwoFactor\Http\Controllers;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laragear\TwoFactor\Http\Middleware\ThrottleWithTwoFactor;
use function trans;

class ThrottlesTwoFactorCodeController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct(protected ConfigContract $config)
    {
        $this->middleware('auth');
        $this->middleware(function (Request $request, Closure $next) {
            $hasKey = $request->session()->has(
                $this->config->get('two-factor.throttle.session_key') . ThrottleWithTwoFactor::EXPIRES_AT_KEY
            );

            if ($hasKey) {
                return $next($request);
            }

            // If the user is not being throttled, we will throw an "HTTP 404 Not found" response.
            throw new HttpResponseException(new Response('', 404));
        });
    }


    /**
     * Return the value used to check if the user should or not be throttled.
     */
    protected function getThrottleValuePath(): string
    {
        return $this->config->get('two-factor.throttle.key') .ThrottleWithTwoFactor::THROTTLED_KEY;
    }

    /**
     * Display the TOTP code confirmation view.
     */
    public function form(ResponseFactory $factory): Response
    {
        return $factory->view('two-factor::confirm');
    }

    /**
     * Confirm the given user's TOTP code.
     */
    public function confirm(Request $request, ResponseFactory $factory): RedirectResponse|JsonResponse
    {
        $request->validate(['2fa_code' => 'required|totp']);

        $request->session()->forget($this->config->get('two-factor.throttle.session_key'));

        return $request->wantsJson()
            ? $factory->json(['message' => trans('two-factor::messages.success')])
            : $factory->redirectToIntended();
    }
}
