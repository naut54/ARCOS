<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Middleware\MiddlewareGroup;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Middleware\SkipRegistry;
use Arcos\Tests\Doubles\AnotherPlainMiddleware;
use Arcos\Tests\Doubles\PlainMiddleware;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class SkipRegistryTest extends TestCase
{
    #[After]
    public function resetRegistry(): void
    {
        SkipRegistry::reset();
    }

    public function test_current_returns_the_same_instance_until_reset(): void
    {
        $this->assertSame(SkipRegistry::current(), SkipRegistry::current());
    }

    public function test_reset_produces_a_new_instance(): void
    {
        $first = SkipRegistry::current();
        SkipRegistry::reset();
        $second = SkipRegistry::current();

        $this->assertNotSame($first, $second);
    }

    public function test_shouldSkip_is_false_until_skip_is_called(): void
    {
        $this->assertFalse(SkipRegistry::current()->shouldSkip(PlainMiddleware::class));
    }

    public function test_skip_a_single_link_by_class(): void
    {
        SkipRegistry::current()->skip(MiddlewareLink::ref(PlainMiddleware::class));

        $this->assertTrue(SkipRegistry::current()->shouldSkip(PlainMiddleware::class));
        $this->assertFalse(SkipRegistry::current()->shouldSkip(AnotherPlainMiddleware::class));
    }

    public function test_skip_a_group_marks_every_contained_link(): void
    {
        $group = MiddlewareGroup::define('audit', [
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(AnotherPlainMiddleware::class),
        ]);

        SkipRegistry::current()->skip($group);

        $this->assertTrue(SkipRegistry::current()->shouldSkip(PlainMiddleware::class));
        $this->assertTrue(SkipRegistry::current()->shouldSkip(AnotherPlainMiddleware::class));
    }

    public function test_reset_clears_previously_skipped_middleware(): void
    {
        SkipRegistry::current()->skip(MiddlewareLink::ref(PlainMiddleware::class));
        SkipRegistry::reset();

        $this->assertFalse(SkipRegistry::current()->shouldSkip(PlainMiddleware::class));
    }
}
