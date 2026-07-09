<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;

class PerRouteTraceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        TraceLog::push('per_route:before');
        $response = $next($request);
        TraceLog::push('per_route:after');

        return $response;
    }
}
