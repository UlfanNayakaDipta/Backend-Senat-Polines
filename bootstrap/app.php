<?php

error_reporting(E_ALL & ~E_DEPRECATED);

use App\Http\Middleware\ApiRoleAuth;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\LocalOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->trimStrings(except: [fn ($request) => $request->is('api/hero-settings')]);
        $middleware->convertEmptyStringsToNull(except: [fn ($request) => $request->is('api/hero-settings')]);
        $middleware->alias([
            'api.token' => ApiTokenAuth::class,
            'api.role' => ApiRoleAuth::class,
            'role' => CheckRole::class,
            'local.only' => LocalOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Respons API tidak boleh membocorkan trace, path, atau nama class internal.
        // abort()/abort_if() melempar HttpException di luar pipeline validasi, jadi
        // tanpa ini APP_DEBUG=true menumpahkan stack trace ke konsumen API.
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = $e->getStatusCode();
            if ($status < 400 || $status >= 500) {
                return null;
            }

            $message = $e->getMessage();

            return response()->json(
                $message !== '' ? ['message' => $message] : null,
                $status,
                $e->getHeaders()
            );
        });
    })->create();
