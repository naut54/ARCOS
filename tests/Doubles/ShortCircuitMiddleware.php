<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;

/**
 * Rejects every request without calling $next(), short-circuiting the chain.
 */
class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return new Response(['success' => false, 'message' => 'rejected'], 403);
    }
}
