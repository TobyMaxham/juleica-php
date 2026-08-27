<?php

declare(strict_types=1);

// Minimal router for the PHP built-in webserver, used by CurlClientTest to
// verify CurlClient against a real HTTP server (header parsing, status code
// extraction, timeout behaviour).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

header('Content-Type: application/json');

switch ($path) {
    case '/ok':
        http_response_code(200);
        echo json_encode(['status' => 'valid', 'valid_till' => '31.12.2025']);
        break;

    case '/not-found':
        http_response_code(404);
        echo json_encode(['message' => 'Not Found']);
        break;

    case '/rate-limited':
        http_response_code(429);
        header('X-RateLimit-Limit: 120');
        header('X-RateLimit-Remaining: 0');
        echo json_encode(['message' => 'Too Many Attempts.']);
        break;

    case '/echo-headers':
        http_response_code(200);
        echo json_encode([
            'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
        ]);
        break;

    case '/slow':
        usleep(1_500_000);
        http_response_code(200);
        echo json_encode(['status' => 'valid']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'No route for ' . $path]);
        break;
}
