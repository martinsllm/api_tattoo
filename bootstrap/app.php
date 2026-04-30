<?php

use App\Helpers\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
    ->withExceptions(function (Exceptions $exceptions): void {
        // 401
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ApiResponse::error('Unauthenticated', 401);
        });

        // 403
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            return ApiResponse::error('Forbidden', 403);
        });

        // 429
        $exceptions->render(function (TooManyRequestsHttpException $e, $request) {
            return ApiResponse::error('Too many requests', 429);
        });

        // 404
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return ApiResponse::error('Resource not found', 404);
        });

        // 422
        $exceptions->render(function (ValidationException $e, $request) {
            return ApiResponse::error('Validation error', 422, $e->errors());
        });

        // regra de negócio
        $exceptions->render(function (DomainException $e, $request) {
            return ApiResponse::error($e->getMessage(), 400);
        });
    })->create();
