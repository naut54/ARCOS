<?php

declare(strict_types=1);

namespace Arcos\Tests\Support;

use Arcos\Tests\Doubles\RealFixtureService;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\TestCase;

class BaseServiceHttpTest extends TestCase
{
    private static $serverProcess;
    private static string $baseUrl;

    #[BeforeClass]
    public static function startServer(): void
    {
        $port    = 8100 + random_int(0, 400);
        $script  = __DIR__ . '/fixtures/http-server.php';
        $command = sprintf('php -S localhost:%d %s', $port, escapeshellarg($script));

        self::$serverProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        self::$baseUrl = "http://localhost:{$port}";

        // Give the built-in server a moment to start listening.
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('localhost', $port, timeout: 0.2);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(50_000);
        }

        self::fail('Fixture HTTP server did not start in time.');
    }

    #[AfterClass]
    public static function stopServer(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    public function test_real_http_call_decodes_a_successful_response(): void
    {
        $service = new RealFixtureService(self::$baseUrl);

        $result = $service->callGet('/ok');

        $this->assertSame('ok', $result['status']);
        $this->assertSame(42, $result['quantity']);
    }

    public function test_real_http_call_with_invalid_json_produces_down(): void
    {
        $service = new RealFixtureService(self::$baseUrl);

        $result = $service->callGet('/badjson');

        $this->assertSame('down', $result['status']);
        $this->assertStringContainsString('Invalid JSON response', $result['reason']);
    }

    public function test_real_http_call_to_an_unreachable_host_produces_down(): void
    {
        $service = new RealFixtureService('http://localhost:1', 1);

        $result = $service->callGet('/ok');

        $this->assertSame('down', $result['status']);
        $this->assertStringContainsString('Could not reach', $result['reason']);
    }

    public function test_real_timeout_actually_fires_and_produces_down(): void
    {
        $service = new RealFixtureService(self::$baseUrl, timeout: 1);

        $start  = microtime(true);
        $result = $service->callGet('/slow');
        $elapsed = microtime(true) - $start;

        $this->assertSame('down', $result['status']);
        $this->assertLessThan(1.4, $elapsed, 'the 1s timeout should fire well before the endpoint\'s 1.5s sleep completes');
    }
}
