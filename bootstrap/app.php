<?php

// Load Docker Secrets into environment if they exist
$secrets = [
    'db_password' => 'DB_PASSWORD',
    'redis_password' => 'REDIS_PASSWORD',
    'app_key' => 'APP_KEY',
];

foreach ($secrets as $fileName => $envKey) {
    $secretPath = "/run/secrets/{$fileName}";
    if (file_exists($secretPath)) {
        $value = trim(file_get_contents($secretPath));
        if ($value !== '') {
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
            putenv("{$envKey}={$value}");
        }
    }
}

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
        // Trust all proxies (needed for Nginx SSL termination)
        $middleware->trustProxies(at: '*');

        // Register middleware aliases for RBAC and Audit
        $middleware->alias([
            'rbac' => \Vandiza\NetsightCore\Http\Middleware\RbacMiddleware::class,
            'audit' => \Vandiza\NetsightCore\Http\Middleware\AuditLogMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \Vandiza\NetsightCore\Http\Middleware\SetPostgresSchema::class,
            \Vandiza\NetsightCore\Http\Middleware\AcceptQueryToken::class,
        ]);

        // Sanctum stateful middleware for SPA authentication
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
