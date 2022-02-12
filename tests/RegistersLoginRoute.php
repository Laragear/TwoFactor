<?php

namespace Tests;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait RegistersLoginRoute
{
    protected function defineWebRoutes($router): void
    {
        $router->post('login', function (Request $request) {
            try {
                return Auth::guard('web')->attempt($request->only('email', 'password'), $request->filled('remember'))
                    ? 'authenticated'
                    : 'unauthenticated';
            } catch (\Throwable $exception) {
                if (! $exception instanceof HttpResponseException) {
                    var_dump([get_class($exception), $exception->getMessage()]);
                }
                throw $exception;
            }
        });
    }
}
