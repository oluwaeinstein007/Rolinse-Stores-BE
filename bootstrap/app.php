<?php

use App\Exceptions\ExchangeRateUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
        $middleware->api(append: [
            \App\Http\Middleware\RespondWithJson::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // GeneralService::convertMoney/newCurrency is called on the checkout
        // path (OrderController::placeOrder, ProductController::confirmPrice)
        // and FinanceController — a forex API outage previously meant a raw
        // 500 with a full stack trace instead of a clean, expected failure.
        $exceptions->render(function (ExchangeRateUnavailableException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Currency conversion is temporarily unavailable. Please try again shortly.',
            ], 503);
        });
    })->create();
