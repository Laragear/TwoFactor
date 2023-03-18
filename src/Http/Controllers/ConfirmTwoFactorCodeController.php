<?php

namespace Laragear\TwoFactor\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use function now;
use function response;
use function trans;

use const INF;

class ConfirmTwoFactorCodeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('throttle:60,1')->only('confirm');
    }

    /**
     * Display the TOTP code confirmation view.
     *
     * @return \Illuminate\Http\Response
     */
    public function form(): Response
    {
        return response()->view('two-factor::confirm');
    }

    /**
     * Confirm the given user's TOTP code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return RedirectResponse|JsonResponse
     */
    public function confirm(Request $request, ConfigContract $config): RedirectResponse|JsonResponse
    {
        $request->validate(['2fa_code' => 'required|totp']);

        $this->extendTotpConfirmationTimeout($request, $config);

        return $request->wantsJson()
            ? response()->json(['message' => trans('two-factor::messages.success')])
            : response()->redirectToIntended();
    }

    /**
     * Reset the TOTP code timeout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    protected function extendTotpConfirmationTimeout(Request $request, ConfigContract $config): void
    {
        [
            'two-factor.confirm.key' => $key,
            'two-factor.confirm.time' => $time
        ] = $config->get([
            'two-factor.confirm.key',
            'two-factor.confirm.time',
        ]);

        // This will let the developer remember the confirmation indefinitely.
        if ($time !== INF) {
            $time = now()->addMinutes($time)->getTimestamp();
        }

        $request->session()->put("$key.confirm.expires_at", $time);
    }
}
