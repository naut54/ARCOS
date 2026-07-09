<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in server. Builds a real Request via
 * Request::fromGlobals() and echoes back what it parsed, so
 * RequestFromGlobalsTest can assert on it from the outside over real HTTP —
 * this is required because getallheaders() does not exist under plain CLI
 * SAPI, only under a real web-facing SAPI (or the built-in server used here).
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Resolvers\PathUriResolver;

$request = Request::fromGlobals(new PathUriResolver());

header('Content-Type: application/json');
echo json_encode([
    'method' => $request->method(),
    'uri'    => $request->uri(),
    'query'  => $request->query(),
    'body'   => $request->body(),
    'header' => $request->header('X-Test-Header'),
]);
