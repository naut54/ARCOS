<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;

class PlainMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
