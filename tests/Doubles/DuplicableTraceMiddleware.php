<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\DuplicableInterface;
use Arcos\Core\Middleware\MiddlewareInterface;

class DuplicableTraceMiddleware implements MiddlewareInterface, DuplicableInterface
{
    public function handle(Request $request, callable $next): Response
    {
        TraceLog::push('duplicable');

        return $next($request);
    }

    public static function allowDuplicates(): bool
    {
        return true;
    }
}
