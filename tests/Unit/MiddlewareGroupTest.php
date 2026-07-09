<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Middleware\MiddlewareGroup;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Tests\Doubles\AnotherPlainMiddleware;
use Arcos\Tests\Doubles\DuplicableTraceMiddleware;
use Arcos\Tests\Doubles\PlainMiddleware;
use LogicException;
use PHPUnit\Framework\TestCase;

class MiddlewareGroupTest extends TestCase
{
    public function test_define_rejects_a_link_with_can_have_group_false(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be added to group');

        MiddlewareGroup::define('g', [
            new MiddlewareLink(PlainMiddleware::class, canHaveGroup: false),
        ]);
    }

    public function test_define_accepts_bare_class_strings_and_wraps_them(): void
    {
        $group = MiddlewareGroup::define('g', [PlainMiddleware::class]);

        $this->assertCount(1, $group->links);
        $this->assertInstanceOf(MiddlewareLink::class, $group->links[0]);
        $this->assertSame(PlainMiddleware::class, $group->links[0]->middleware);
    }

    public function test_define_throws_on_duplicate_middleware_within_the_group(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate middleware');

        MiddlewareGroup::define('g', [
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(PlainMiddleware::class),
        ]);
    }

    public function test_define_allows_duplicates_when_middleware_opts_in(): void
    {
        $group = MiddlewareGroup::define('g', [
            new MiddlewareLink(DuplicableTraceMiddleware::class),
            new MiddlewareLink(DuplicableTraceMiddleware::class),
        ]);

        $this->assertCount(2, $group->links);
    }

    public function test_define_allows_distinct_middleware_classes(): void
    {
        $group = MiddlewareGroup::define('g', [
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(AnotherPlainMiddleware::class),
        ]);

        $this->assertCount(2, $group->links);
    }

    public function test_ref_is_a_name_only_reference_with_no_links(): void
    {
        $ref = MiddlewareGroup::ref('some-group');

        $this->assertSame('some-group', $ref->name);
        $this->assertTrue($ref->isRef());
    }

    public function test_define_is_not_a_ref(): void
    {
        $group = MiddlewareGroup::define('g', [PlainMiddleware::class]);

        $this->assertFalse($group->isRef());
    }
}
