<?php

declare(strict_types=1);

namespace Arcos\Tests\Support;

use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\TestCase;

class RequestFromGlobalsTest extends TestCase
{
    private static $serverProcess;
    private static string $baseUrl;

    #[BeforeClass]
    public static function startServer(): void
    {
        $port    = 8200 + random_int(0, 400);
        $script  = __DIR__ . '/fixtures/request-echo-server.php';
        $command = sprintf('php -S localhost:%d %s', $port, escapeshellarg($script));

        self::$serverProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        self::$baseUrl = "http://localhost:{$port}";

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

    private function fetch(string $path, string $method = 'GET', array $headers = [], ?string $body = null): array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => $method,
                'header'  => implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)),
                'content' => $body,
            ],
        ]);

        $raw = file_get_contents(self::$baseUrl . $path, context: $context);

        return json_decode($raw, associative: true);
    }

    public function test_reads_method_uri_and_query_from_a_real_request(): void
    {
        $result = $this->fetch('/products?page=2');

        $this->assertSame('GET', $result['method']);
        $this->assertSame('/products', $result['uri']);
        $this->assertSame(['page' => '2'], $result['query']);
    }

    public function test_reads_a_custom_header_via_getallheaders(): void
    {
        $result = $this->fetch('/x', headers: ['X-Test-Header' => 'hello']);

        $this->assertSame('hello', $result['header']);
    }

    public function test_reads_json_body_on_post(): void
    {
        $result = $this->fetch(
            '/products',
            'POST',
            ['Content-Type' => 'application/json'],
            json_encode(['name' => 'Widget']),
        );

        $this->assertSame('POST', $result['method']);
        $this->assertSame(['name' => 'Widget'], $result['body']);
    }

    public function test_reads_form_urlencoded_body_on_patch(): void
    {
        $result = $this->fetch(
            '/products/1',
            'PATCH',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'price=12.50',
        );

        $this->assertSame('PATCH', $result['method']);
        $this->assertSame(['price' => '12.50'], $result['body']);
    }
}
