<?php

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InsufficientStockException $e, Request $request) {
            return response()->json([
                'error' => 'Insufficient Stock',
                'message' => "Insufficient stock for product with ID {$e->productId}. Requested: {$e->requested}, available: {$e->available}",
            ], 422);
        });

        $exceptions->render(function (ProductNotFoundException $e, Request $request) {
            return response()->json([
                'error' => 'Products Not Found',
                'message' => 'The following products were not found or have been deleted',
                'missing_ids' => $e->missingIds,
            ], 422);
        });
    })->create();
