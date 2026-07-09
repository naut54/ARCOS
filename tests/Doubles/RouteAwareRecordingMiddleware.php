<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;
use Arcos\Core\Middleware\RouteAwareInterface;

class RouteAwareRecordingMiddleware implements MiddlewareInterface, RouteAwareInterface
{
    private array $allowedMethods = [];

    public function withAllowedMethods(array $methods): static
    {
        $clone = clone $this;
        $clone->allowedMethods = $methods;

        return $clone;
    }

    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function handle(Request $request, callable $next): Response
    {
        TraceLog::push('route-aware:' . implode(',', $this->allowedMethods));

        return $next($request);
    }
}
