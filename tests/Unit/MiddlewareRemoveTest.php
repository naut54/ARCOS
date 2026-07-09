<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Middleware\MiddlewareRemove;
use Arcos\Tests\Doubles\PlainMiddleware;
use PHPUnit\Framework\TestCase;

class MiddlewareRemoveTest extends TestCase
{
    public function test_ref_wraps_the_middleware_class_name(): void
    {
        $remove = MiddlewareRemove::ref(PlainMiddleware::class);

        $this->assertSame(PlainMiddleware::class, $remove->middleware);
    }
}
