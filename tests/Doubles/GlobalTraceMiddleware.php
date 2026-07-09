<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;

class GlobalTraceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        TraceLog::push('global:before');
        $response = $next($request);
        TraceLog::push('global:after');

        return $response;
    }
}
