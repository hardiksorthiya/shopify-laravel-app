<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'billing.active' => \App\Http\Middleware\EnsureShopBillingIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => '/');
        $middleware->validateCsrfTokens(except: [
            'api/price-settings',
            'api/product-variant-price',
            'billing/create-charge',
            'webhooks/customers/data_request',
            'webhooks/customers/redact',
            'webhooks/shop/redact',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
