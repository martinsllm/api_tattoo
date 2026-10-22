<?php

use App\Enums\ApiError;
use App\Helpers\ApiErrorMessage;
use App\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withExceptions(function (Exceptions $exceptions): void {
        // 401
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ApiResponse::error(ApiError::UNAUTHENTICATED->value, 401);
        });

        // 403 — link de verificação assinado inválido ou expirado
        $exceptions->render(function (InvalidSignatureException $e, $request) {
            return ApiResponse::error('Link de verificação inválido ou expirado.', 403);
        });

        // 403
        $exceptions->render(function (AuthorizationException $e, $request) {
            return ApiResponse::error(ApiErrorMessage::resolve($e->getMessage()), 403);
        });

        // 403
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            return ApiResponse::error(ApiErrorMessage::resolve($e->getMessage()), 403);
        });

        // 403
        $exceptions->render(function (UnauthorizedException $e, $request) {
            return ApiResponse::error(ApiError::FORBIDDEN->value, 403);
        });

        // 403 — abort(403) lança HttpException genérica (ex.: DocsAccess)
        $exceptions->render(function (HttpException $e, $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            return ApiResponse::error(ApiErrorMessage::resolve($e->getMessage()), 403);
        });

        // 429
        $exceptions->render(function (TooManyRequestsHttpException $e, $request) {
            return ApiResponse::error(ApiError::TOO_MANY_REQUESTS->value, 429);
        });

        // 404
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return ApiResponse::error(ApiError::NOT_FOUND->value, 404);
        });

        // 422
        $exceptions->render(function (ValidationException $e, $request) {
            return ApiResponse::error(ApiError::VALIDATION_ERROR->value, 422, $e->errors());
        });

        // regra de negócio
        $exceptions->render(function (DomainException $e, $request) {
            return ApiResponse::error($e->getMessage(), 400);
        });

        // 500
        $exceptions->render(function (Exception $e, $request) {
            if (app()->hasDebugModeEnabled()) {
                return ApiResponse::error($e->getMessage(), 500);
            }

            return ApiResponse::error(ApiError::INTERNAL_SERVER_ERROR->value, 500);
        });
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })->create();
