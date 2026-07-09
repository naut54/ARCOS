<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Tests\Doubles\MandatoryTraceMiddleware;
use Arcos\Tests\Doubles\NotAMiddleware;
use Arcos\Tests\Doubles\PlainMiddleware;
use LogicException;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class MiddlewareLinkTest extends TestCase
{
    #[After]
    public function resetNamedRegistry(): void
    {
        MiddlewareLink::resetNamedRegistry();
    }

    public function test_class_not_implementing_middleware_interface_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement MiddlewareInterface');

        new MiddlewareLink(NotAMiddleware::class);
    }

    public function test_mandatory_without_mandatory_interface_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not implement MandatoryMiddlewareInterface');

        new MiddlewareLink(PlainMiddleware::class, isMandatory: true);
    }

    public function test_mandatory_and_skippable_together_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be both mandatory and skippable');

        new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true, isSkippable: true);
    }

    public function test_mandatory_with_mandatory_interface_is_valid(): void
    {
        $link = new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true);

        $this->assertTrue($link->isMandatory);
    }

    public function test_ref_resolves_a_registered_name_to_its_middleware_class(): void
    {
        new MiddlewareLink(PlainMiddleware::class, name: 'my-rule');

        $resolved = MiddlewareLink::ref('my-rule');

        $this->assertSame(PlainMiddleware::class, $resolved->middleware);
    }

    public function test_ref_falls_back_to_a_literal_class_when_name_is_unregistered(): void
    {
        $resolved = MiddlewareLink::ref(PlainMiddleware::class);

        $this->assertSame(PlainMiddleware::class, $resolved->middleware);
    }

    public function test_ref_with_unregistered_name_that_is_not_a_class_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement MiddlewareInterface');

        MiddlewareLink::ref('totally-unregistered-name');
    }

    public function test_duplicate_name_for_a_different_class_throws(): void
    {
        new MiddlewareLink(PlainMiddleware::class, name: 'shared-name');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Link names must be unique');

        new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true, name: 'shared-name');
    }

    public function test_same_name_for_the_same_class_is_allowed(): void
    {
        new MiddlewareLink(PlainMiddleware::class, name: 'reused-name');

        $link = new MiddlewareLink(PlainMiddleware::class, name: 'reused-name');

        $this->assertSame(PlainMiddleware::class, $link->middleware);
    }

    public function test_reset_named_registry_clears_registered_names(): void
    {
        new MiddlewareLink(PlainMiddleware::class, name: 'to-be-cleared');
        MiddlewareLink::resetNamedRegistry();

        // No longer registered, so ref() now falls back to literal-class
        // resolution and throws because 'to-be-cleared' isn't a real class.
        $this->expectException(LogicException::class);

        MiddlewareLink::ref('to-be-cleared');
    }
}
