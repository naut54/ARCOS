<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Http\Response;
use Arcos\Core\Routing\Attributes\Routable;
use Arcos\Core\Routing\DispatchResult;
use Arcos\Core\Routing\SubdomainContext;
use Arcos\Tests\Doubles\FixedUriResolver;
use PHPUnit\Framework\TestCase;

class ValueObjectsTest extends TestCase
{
    public function test_routable_stores_its_methods(): void
    {
        $routable = new Routable(methods: ['GET', 'POST']);

        $this->assertSame(['GET', 'POST'], $routable->methods);
    }

    public function test_dispatch_result_stores_response_and_skipped_mandatory(): void
    {
        $response = new Response(['success' => true], 200);
        $result   = new DispatchResult($response, [1, 2]);

        $this->assertSame($response, $result->response);
        $this->assertSame([1, 2], $result->skippedMandatory);
    }

    public function test_subdomain_context_defaults(): void
    {
        $resolver = new FixedUriResolver();
        $context  = new SubdomainContext('api', $resolver, ['g']);

        $this->assertSame('api', $context->subdomain);
        $this->assertSame($resolver, $context->resolver);
        $this->assertSame(['g'], $context->middlewareGroups);
        $this->assertFalse($context->autoResolve);
        $this->assertSame('App\\Controllers', $context->controllersNamespace);
    }

    public function test_subdomain_context_accepts_auto_resolve_overrides(): void
    {
        $context = new SubdomainContext(
            'internal',
            new FixedUriResolver(),
            [],
            autoResolve: true,
            controllersNamespace: 'App\\Controllers\\Internal',
        );

        $this->assertTrue($context->autoResolve);
        $this->assertSame('App\\Controllers\\Internal', $context->controllersNamespace);
    }
}
