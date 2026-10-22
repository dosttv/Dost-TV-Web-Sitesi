<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Resized image variants: deliberately outside the "web" group so
            // responses set no session cookies and stay cacheable at the CDN.
            \Illuminate\Support\Facades\Route::get('/img/{width}/{path}', \App\Http\Controllers\MediaResizeController::class)
                ->where(['width' => '[0-9]+', 'path' => '.+'])
                ->name('media.resize');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: '/admin/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if (! $request->isMethod('GET') || $request->is('admin*')) {
                return;
            }

            $path = strtolower($request->path());

            // Ignore scanner / bot URL patterns and static noise
            $ignoredPatterns = [
                'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'xmlrpc',
                '.env', '.git', '.config', 'phpunit', 'phpmyadmin', 'pma',
                'favicon.ico', 'robots.txt', 'sitemap.xml', 'apple-touch-icon',
            ];

            foreach ($ignoredPatterns as $pattern) {
                if (str_contains($path, $pattern)) {
                    return;
                }
            }

            if (str_ends_with($path, '.php')) {
                return;
            }

            try {
                app(\App\Services\Analytics\AnalyticsService::class)->logNotFound($request->path(), $request->header('referer'));
            } catch (\Throwable $ex) {
                // Silent isolation
            }
        });
    })->create();
