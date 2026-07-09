<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Request;
use Arcos\Core\Middleware\MiddlewareGroup;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Middleware\MiddlewareRemove;
use Arcos\Core\Middleware\SkipRegistry;
use Arcos\Core\Routing\Router;
use Arcos\Tests\Doubles\AnotherPlainMiddleware;
use Arcos\Tests\Doubles\DuplicableTraceMiddleware;
use Arcos\Tests\Doubles\FixedUriResolver;
use Arcos\Tests\Doubles\FixtureController;
use Arcos\Tests\Doubles\GlobalTraceMiddleware;
use Arcos\Tests\Doubles\GroupTraceMiddleware;
use Arcos\Tests\Doubles\MandatoryTraceMiddleware;
use Arcos\Tests\Doubles\PerRouteTraceMiddleware;
use Arcos\Tests\Doubles\PlainMiddleware;
use Arcos\Tests\Doubles\RouteAwareRecordingMiddleware;
use Arcos\Tests\Doubles\ShortCircuitMiddleware;
use Arcos\Tests\Doubles\TraceLog;
use LogicException;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    #[Before]
    public function resetSharedState(): void
    {
        TraceLog::reset();
        SkipRegistry::reset();
        MiddlewareLink::resetNamedRegistry();
    }

    #[After]
    public function resetSharedStateAfter(): void
    {
        TraceLog::reset();
        SkipRegistry::reset();
        MiddlewareLink::resetNamedRegistry();
    }

    private function makeContainer(): Container
    {
        $container = new Container();
        $container->bind(FixtureController::class, fn() => new FixtureController());
        $container->bind(PlainMiddleware::class, fn() => new PlainMiddleware());
        $container->bind(AnotherPlainMiddleware::class, fn() => new AnotherPlainMiddleware());
        $container->bind(MandatoryTraceMiddleware::class, fn() => new MandatoryTraceMiddleware());
        $container->bind(ShortCircuitMiddleware::class, fn() => new ShortCircuitMiddleware());
        $container->bind(RouteAwareRecordingMiddleware::class, fn() => new RouteAwareRecordingMiddleware());
        $container->bind(DuplicableTraceMiddleware::class, fn() => new DuplicableTraceMiddleware());
        $container->bind(GlobalTraceMiddleware::class, fn() => new GlobalTraceMiddleware());
        $container->bind(GroupTraceMiddleware::class, fn() => new GroupTraceMiddleware());
        $container->bind(PerRouteTraceMiddleware::class, fn() => new PerRouteTraceMiddleware());

        return $container;
    }

    private function request(string $method = 'GET', string $uri = '/products'): Request
    {
        return new Request($method, $uri, [], [], []);
    }

    // Subdomain registration

    public function test_registering_the_same_subdomain_twice_throws(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already registered');

        $router->registerSubdomain('api', new FixedUriResolver());
    }

    public function test_activating_an_unregistered_subdomain_throws(): void
    {
        $router = new Router();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has not been registered');

        $router->setActiveSubdomain('api');
    }

    public function test_active_resolver_before_activation_throws(): void
    {
        $router = new Router();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No active subdomain set');

        $router->activeResolver();
    }

    // Route declaration

    public function test_add_outside_a_subdomain_block_throws(): void
    {
        $router = new Router();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outside a subdomain() block');

        $router->add('GET', '/products', FixtureController::class, 'index');
    }

    // Resolution / dispatch

    public function test_dispatch_resolves_an_exact_match(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $result = $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame(200, $result->response->status());
    }

    public function test_dispatch_returns_404_when_uri_does_not_match(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $result = $router->dispatch($this->request('GET', '/nope'), $this->makeContainer());

        $this->assertSame(404, $result->response->status());
    }

    public function test_dispatch_returns_405_when_uri_matches_but_method_does_not(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $result = $router->dispatch($this->request('POST', '/products'), $this->makeContainer());

        $this->assertSame(405, $result->response->status());
    }

    public function test_routes_from_inactive_subdomains_are_invisible(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->registerSubdomain('admin', new FixedUriResolver());
        $router->subdomain('admin', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->setActiveSubdomain('api');

        $result = $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame(404, $result->response->status());
    }

    // Regression: duplicate detection within a single call (not just against pre-existing entries)

    public function test_always_throws_on_duplicate_middleware_within_the_same_call(): void
    {
        $router = new Router();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate middleware');

        $router->always([
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(PlainMiddleware::class),
        ]);
    }

    public function test_middleware_throws_on_duplicate_within_the_same_per_route_call(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate middleware');

        $router->middleware('GET', '/products', [
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(PlainMiddleware::class),
        ]);
    }

    public function test_always_allows_distinct_middleware(): void
    {
        $router = new Router();

        $router->always([
            new MiddlewareLink(PlainMiddleware::class),
            new MiddlewareLink(AnotherPlainMiddleware::class),
        ]);

        $this->addToAssertionCount(1); // no exception thrown
    }

    // Regression: mandatory middleware must still run on 404/405

    public function test_mandatory_global_middleware_is_reported_as_skipped_on_404(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->always([
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true, canHaveGroup: false),
        ]);

        $result = $router->dispatch($this->request('GET', '/nope'), $this->makeContainer());

        $this->assertSame(404, $result->response->status());
        $this->assertCount(1, $result->skippedMandatory);
        $this->assertInstanceOf(MandatoryTraceMiddleware::class, $result->skippedMandatory[0]);
    }

    public function test_mandatory_global_middleware_is_reported_as_skipped_on_405(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->always([
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true, canHaveGroup: false),
        ]);

        $result = $router->dispatch($this->request('POST', '/products'), $this->makeContainer());

        $this->assertSame(405, $result->response->status());
        $this->assertCount(1, $result->skippedMandatory);
    }

    public function test_non_mandatory_global_middleware_is_not_reported_as_skipped_on_404(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->always([
            new MiddlewareLink(PlainMiddleware::class),
        ]);

        $result = $router->dispatch($this->request('GET', '/nope'), $this->makeContainer());

        $this->assertSame([], $result->skippedMandatory);
    }

    // Two-pass execution

    public function test_short_circuit_prevents_downstream_middleware_but_mandatory_still_reported_skipped(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->middleware('GET', '/products', [
            new MiddlewareLink(ShortCircuitMiddleware::class),
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true),
        ]);

        $result = $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame(403, $result->response->status());
        $this->assertCount(1, $result->skippedMandatory);
        $this->assertInstanceOf(MandatoryTraceMiddleware::class, $result->skippedMandatory[0]);
    }

    public function test_mandatory_middleware_that_ran_is_not_reported_as_skipped(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->middleware('GET', '/products', [
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true),
        ]);

        $result = $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame(200, $result->response->status());
        $this->assertSame([], $result->skippedMandatory);
        $this->assertContains('mandatory:handle', TraceLog::all());
    }

    // Onion order

    public function test_execution_order_is_global_then_group_then_per_route(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver(), middlewareGroups: ['g']);
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->always([new MiddlewareLink(GlobalTraceMiddleware::class, canHaveGroup: false)]);
        $router->group('g', [new MiddlewareLink(GroupTraceMiddleware::class)]);
        $router->middleware('GET', '/products', [new MiddlewareLink(PerRouteTraceMiddleware::class)]);

        $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame([
            'global:before',
            'group:before',
            'per_route:before',
            'per_route:after',
            'group:after',
            'global:after',
        ], TraceLog::all());
    }

    // RouteAwareInterface

    public function test_route_aware_middleware_receives_the_full_allowed_method_set(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
            $r->add('POST', '/products', FixtureController::class, 'index');
        });
        $router->middleware('GET', '/products', [new MiddlewareLink(RouteAwareRecordingMiddleware::class)]);

        $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $trace = TraceLog::all();
        $this->assertCount(1, $trace);
        $this->assertStringContainsString('GET', $trace[0]);
        $this->assertStringContainsString('POST', $trace[0]);
    }

    // Skippable middleware

    public function test_skippable_middleware_is_bypassed_when_skipped(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->middleware('GET', '/products', [
            new MiddlewareLink(GroupTraceMiddleware::class, isSkippable: true),
        ]);

        SkipRegistry::current()->skip(MiddlewareLink::ref(GroupTraceMiddleware::class));

        $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame([], TraceLog::all());
    }

    public function test_skippable_middleware_runs_when_not_skipped(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->middleware('GET', '/products', [
            new MiddlewareLink(GroupTraceMiddleware::class, isSkippable: true),
        ]);

        $router->dispatch($this->request('GET', '/products'), $this->makeContainer());

        $this->assertSame(['group:before', 'group:after'], TraceLog::all());
    }

    // MiddlewareRemove

    public function test_middleware_remove_removes_a_non_mandatory_entry(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
            $r->add('GET', '/health', FixtureController::class, 'index');
        });
        $router->always([new MiddlewareLink(GlobalTraceMiddleware::class, canHaveGroup: false)]);
        $router->middleware('GET', '/health', [MiddlewareRemove::ref(GlobalTraceMiddleware::class)]);

        $router->dispatch($this->request('GET', '/health'), $this->makeContainer());

        $this->assertSame([], TraceLog::all());
    }

    public function test_middleware_remove_throws_when_removing_a_mandatory_entry(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/health', FixtureController::class, 'index');
        });
        $router->always([
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true, canHaveGroup: false),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('declared as mandatory');

        $router->middleware('GET', '/health', [MiddlewareRemove::ref(MandatoryTraceMiddleware::class)]);
    }

    public function test_middleware_remove_throws_when_target_is_absent(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/health', FixtureController::class, 'index');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not present in the chain');

        $router->middleware('GET', '/health', [MiddlewareRemove::ref(PlainMiddleware::class)]);
    }

    // Group expansion

    public function test_referencing_an_undefined_group_in_per_route_middleware_throws(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not defined');

        $router->middleware('GET', '/products', [MiddlewareGroup::ref('undefined-group')]);
    }

    public function test_referencing_an_undefined_group_in_a_subdomain_throws_on_dispatch(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver(), middlewareGroups: ['undefined-group']);
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not defined');

        $router->dispatch($this->request('GET', '/products'), $this->makeContainer());
    }

    public function test_defining_the_same_group_name_twice_throws(): void
    {
        $router = new Router();
        $router->group('g', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already defined');

        $router->group('g', []);
    }

    // dumpRoutes() / dumpGroups()

    public function test_dump_routes_reports_method_uri_controller_action_and_middleware(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver(), middlewareGroups: ['g']);
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });
        $router->always([new MiddlewareLink(GlobalTraceMiddleware::class, canHaveGroup: false)]);
        $router->group('g', [new MiddlewareLink(GroupTraceMiddleware::class)]);
        $router->middleware('GET', '/products', [new MiddlewareLink(PerRouteTraceMiddleware::class)]);

        $dump = $router->dumpRoutes();

        $this->assertCount(1, $dump);
        $this->assertSame('GET', $dump[0]['method']);
        $this->assertSame('/products', $dump[0]['uri']);
        $this->assertSame(FixtureController::class, $dump[0]['controller']);
        $this->assertSame('index', $dump[0]['action']);
        $this->assertCount(3, $dump[0]['middleware']);
        $this->assertSame('global', $dump[0]['middleware'][0]['layer']);
        $this->assertSame('subdomain_group', $dump[0]['middleware'][1]['layer']);
        $this->assertSame('per_route', $dump[0]['middleware'][2]['layer']);
    }

    public function test_dump_groups_reports_defined_groups(): void
    {
        $router = new Router();
        $router->group('g', [new MiddlewareLink(PlainMiddleware::class, name: 'p')]);

        $dump = $router->dumpGroups();

        $this->assertArrayHasKey('g', $dump);
        $this->assertSame('g', $dump['g']['name']);
        $this->assertSame(PlainMiddleware::class, $dump['g']['links'][0]['class']);
        $this->assertSame('p', $dump['g']['links'][0]['name']);
    }
}
