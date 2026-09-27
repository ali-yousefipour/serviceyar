<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ServiceYar\Http\Response;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && ($path === '/api/health' || $path === '/api/v1/health')) {
    Response::json([
        'ok' => true,
        'service' => config('APP_NAME', 'ServiceYar'),
        'environment' => config('APP_ENV', 'local'),
        'version' => '0.1.0',
        'time' => gmdate('c'),
    ]);
}

Response::json([
    'ok' => false,
    'error' => [
        'code' => 'NOT_FOUND',
        'message' => 'مسیر مورد نظر پیدا نشد.',
    ],
], 404);
