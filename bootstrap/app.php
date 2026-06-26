<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The submitted data is invalid.',
                    'fields' => $exception->errors(),
                ],
                'meta' => ['request_id' => $request->header('X-Request-ID')],
            ], 422);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            return response()->json([
                'error' => [
                    'code' => match ($status) {
                        401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                        409 => 'STATE_CONFLICT', 429 => 'RATE_LIMITED', default => 'HTTP_ERROR',
                    },
                    'message' => $exception->getMessage() ?: match ($status) {
                        401 => 'Authentication is required.', 403 => 'This action is not allowed.',
                        404 => 'The requested resource was not found.', 409 => 'The resource state has changed.',
                        429 => 'Too many requests.', default => 'The request could not be completed.',
                    },
                ],
                'meta' => ['request_id' => $request->header('X-Request-ID')],
            ], $status, $exception->getHeaders());
        });
    })->create();

// Redirect storage path to /tmp on Vercel read-only environment
if (env('RUNNING_IN_VERCEL') === 'true') {
    $app->useStoragePath('/tmp/storage');
}

return $app;
