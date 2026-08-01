<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // Answer with JSON everywhere under /api, even when the client forgot
        // to send an Accept header — an HTML error page is never useful there.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Route model binding failures arrive here already converted, carrying
        // a message that names the model class and id. Replace it: which
        // internal class backs a route is nobody else's business.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/*')
                ? response()->json(['message' => 'Resource not found.'], Response::HTTP_NOT_FOUND)
                : null;
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return $request->is('api/*')
                ? response()->json(['message' => 'This action is unauthorized.'], Response::HTTP_FORBIDDEN)
                : null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return $request->is('api/*')
                ? response()->json(['message' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED)
                : null;
        });

        // Catch-all so an unexpected failure still answers in the API's own
        // shape rather than an HTML page or a stack trace.
        //
        // Deliberately steps aside for: HTTP exceptions (already carry a status
        // and a safe message), validation, and authentication — the framework
        // renders those correctly, and they are handled after the callbacks.
        // While debugging locally, the detailed response is left intact.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException) {
                return null;
            }

            return response()->json(
                ['message' => 'Server error.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
