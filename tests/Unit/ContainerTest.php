<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class ContainerTest extends TestCase
{
    public function test_bind_produces_a_fresh_instance_per_make(): void
    {
        $container = new Container();
        $container->bind(stdClass::class, fn() => new stdClass());

        $first  = $container->make(stdClass::class);
        $second = $container->make(stdClass::class);

        $this->assertNotSame($first, $second);
    }

    public function test_singleton_produces_the_same_instance_every_time(): void
    {
        $container = new Container();
        $container->singleton(stdClass::class, fn() => new stdClass());

        $first  = $container->make(stdClass::class);
        $second = $container->make(stdClass::class);

        $this->assertSame($first, $second);
    }

    public function test_make_throws_for_an_unregistered_binding(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No binding found for [stdClass]');

        $container->make(stdClass::class);
    }

    public function test_has_reflects_registered_bindings(): void
    {
        $container = new Container();

        $this->assertFalse($container->has(stdClass::class));

        $container->bind(stdClass::class, fn() => new stdClass());

        $this->assertTrue($container->has(stdClass::class));
    }

    public function test_singleton_factory_receives_the_container_itself(): void
    {
        $container = new Container();
        $container->singleton('a', fn() => 'value-a');
        $container->singleton('b', fn(Container $c) => $c->make('a') . '+b');

        $this->assertSame('value-a+b', $container->make('b'));
    }
}
