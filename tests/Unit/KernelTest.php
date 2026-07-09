<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Kernel;
use Arcos\Core\Http\Request;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Middleware\SkipRegistry;
use Arcos\Core\Routing\Router;
use Arcos\Tests\Doubles\FixedUriResolver;
use Arcos\Tests\Doubles\FixtureController;
use Arcos\Tests\Doubles\GroupTraceMiddleware;
use Arcos\Tests\Doubles\MandatoryTraceMiddleware;
use Arcos\Tests\Doubles\ShortCircuitMiddleware;
use Arcos\Tests\Doubles\ThrowingMiddleware;
use Arcos\Tests\Doubles\TraceLog;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    private string $errorLogFile;
    private string $originalErrorLog;

    #[Before]
    public function resetSharedState(): void
    {
        TraceLog::reset();
        SkipRegistry::reset();
        MiddlewareLink::resetNamedRegistry();

        $this->originalErrorLog = ini_get('error_log');
        $this->errorLogFile     = tempnam(sys_get_temp_dir(), 'arcos-kernel-test-');
        ini_set('error_log', $this->errorLogFile);
    }

    #[After]
    public function resetSharedStateAfter(): void
    {
        TraceLog::reset();
        SkipRegistry::reset();
        MiddlewareLink::resetNamedRegistry();

        ini_set('error_log', $this->originalErrorLog);
        @unlink($this->errorLogFile);
    }

    private function makeContainer(): Container
    {
        $container = new Container();
        $container->bind(FixtureController::class, fn() => new FixtureController());
        $container->bind(ShortCircuitMiddleware::class, fn() => new ShortCircuitMiddleware());
        $container->bind(MandatoryTraceMiddleware::class, fn() => new MandatoryTraceMiddleware());
        $container->bind(GroupTraceMiddleware::class, fn() => new GroupTraceMiddleware());
        $container->bind(ThrowingMiddleware::class, fn() => new ThrowingMiddleware());

        return $container;
    }

    private function router(): Router
    {
        $router = new Router();
        $router->registerSubdomain('api', new FixedUriResolver());
        $router->setActiveSubdomain('api');
        $router->subdomain('api', function (Router $r) {
            $r->add('GET', '/products', FixtureController::class, 'index');
        });

        return $router;
    }

    public function test_handle_resets_skip_registry_before_dispatch(): void
    {
        $router = $this->router();
        $router->middleware('GET', '/products', [
            new MiddlewareLink(GroupTraceMiddleware::class, isSkippable: true),
        ]);

        // Simulate stale skip state left over from a previous request.
        SkipRegistry::current()->skip(MiddlewareLink::ref(GroupTraceMiddleware::class));

        $kernel = new Kernel($this->makeContainer(), $router);

        ob_start();
        $kernel->handle(new Request('GET', '/products', [], [], []));
        ob_end_clean();

        $this->assertSame(['group:before', 'group:after'], TraceLog::all());
    }

    public function test_handle_runs_the_mandatory_sweep_for_skipped_middleware(): void
    {
        $router = $this->router();
        $router->middleware('GET', '/products', [
            new MiddlewareLink(ShortCircuitMiddleware::class),
            new MiddlewareLink(MandatoryTraceMiddleware::class, isMandatory: true),
        ]);

        $kernel = new Kernel($this->makeContainer(), $router);

        ob_start();
        $kernel->handle(new Request('GET', '/products', [], [], []));
        $output = ob_get_clean();

        $this->assertContains('mandatory:handleMandatory', TraceLog::all());
        $decoded = json_decode($output, associative: true);
        $this->assertFalse($decoded['success']);
    }

    public function test_handle_catches_unhandled_exceptions_and_returns_a_generic_error(): void
    {
        $router = $this->router();
        $router->middleware('GET', '/products', [
            new MiddlewareLink(ThrowingMiddleware::class),
        ]);

        $kernel = new Kernel($this->makeContainer(), $router);

        ob_start();
        $kernel->handle(new Request('GET', '/products', [], [], []));
        $output = ob_get_clean();

        $decoded = json_decode($output, associative: true);

        $this->assertFalse($decoded['success']);
        $this->assertSame('SYS-001', $decoded['error_code']);
        $this->assertStringNotContainsString('boom', $output);
        $this->assertSame(500, http_response_code());
    }

    public function test_handle_logs_the_real_exception_detail_server_side(): void
    {
        $router = $this->router();
        $router->middleware('GET', '/products', [
            new MiddlewareLink(ThrowingMiddleware::class),
        ]);

        $kernel = new Kernel($this->makeContainer(), $router);

        ob_start();
        $kernel->handle(new Request('GET', '/products', [], [], []));
        ob_end_clean();

        $logged = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('boom: sensitive internal detail', $logged);
    }
}
