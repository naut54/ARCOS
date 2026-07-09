<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MandatoryMiddlewareInterface;
use Arcos\Core\Middleware\MiddlewareInterface;

class MandatoryTraceMiddleware implements MiddlewareInterface, MandatoryMiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        TraceLog::push('mandatory:handle');

        return $next($request);
    }

    public function handleMandatory(Request $request, Response $response): Response
    {
        TraceLog::push('mandatory:handleMandatory');

        return $response;
    }
}
