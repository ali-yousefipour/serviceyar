<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ServiceYar\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function config(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none';');
