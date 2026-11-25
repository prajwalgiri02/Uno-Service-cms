<?php

use App\Exceptions\OtpException;
use App\Http\Middleware\EnsureAccessToken;
use App\Http\Middleware\EnsureUserNotBlocked;
use App\Http\Middleware\EnsureVendorShop;
use App\Http\Middleware\HandleInertiaRequests;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('otp:prune')->hourly();
        $schedule->command('nudges:mark-ignored')->hourly();
        $schedule->command('nudges:weekly-summary')->weeklyOn(0, '09:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/cms/login');
        $middleware->redirectUsersTo(RouteServiceProvider::HOME);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'jwt.access' => EnsureAccessToken::class,
            'not-blocked' => EnsureUserNotBlocked::class,
            'vendor.shop' => EnsureVendorShop::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every API error uses the { status, message, errors } envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'status' => 422,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof OtpException) {
                return response()->json([
                    'status' => 422,
                    'message' => $e->getMessage(),
                    'errors' => [$e->field => [$e->getMessage()]],
                    'retry_after' => $e->retryAfterSeconds,
                ], 422);
            }

            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'status' => 429,
                    'message' => 'Too many attempts. Please try again later.',
                    'errors' => null,
                ], 429, $e->getHeaders());
            }

            [$status, $message] = match (true) {
                $e instanceof AuthenticationException => [401, 'Unauthenticated.'],
                $e instanceof UnauthorizedException => [403, 'You do not have permission to perform this action.'],
                $e instanceof AuthorizationException => [403, $e->getMessage() ?: 'This action is unauthorized.'],
                $e instanceof AccessDeniedHttpException => [403, 'This action is unauthorized.'],
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => [404, 'Resource not found.'],
                $e instanceof MethodNotAllowedHttpException => [405, 'Method not allowed.'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Request failed.'],
                default => [500, config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.'],
            };

            return response()->json([
                'status' => $status,
                'message' => $message,
                'errors' => null,
            ], $status);
        });
    })->create();
