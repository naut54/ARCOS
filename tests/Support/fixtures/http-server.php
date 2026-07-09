<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in server, used by BaseServiceHttpTest to
 * exercise BaseService's real file_get_contents-based transport end-to-end.
 */

header('Content-Type: application/json');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

match ($uri) {
    '/ok'       => print(json_encode(['status' => 'ok', 'quantity' => 42])),
    '/badjson'  => print('not-json{{{'),
    '/slow'     => (function () {
        usleep(1_500_000); // 1.5s — longer than the 1s timeout BaseServiceHttpTest configures
        echo json_encode(['status' => 'ok']);
    })(),
    default     => (function () {
        http_response_code(404);
        echo json_encode(['status' => 'down', 'reason' => 'not found']);
    })(),
};
