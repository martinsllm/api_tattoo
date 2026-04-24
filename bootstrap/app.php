<?php

use App\Exceptions\Handler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function ($exceptions): void {
        // 403
        $exceptions->render(function (AuthorizationException $e, $request) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        });

        // 404
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            return response()->json([
                'message' => 'Resource not found'
            ], 404);
        });

        // 422
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        });

        // regra de negócio
        $exceptions->render(function (\DomainException $e, $request) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        });
    })->create();
