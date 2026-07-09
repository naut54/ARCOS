<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Tests\Doubles\FixtureService;
use PHPUnit\Framework\TestCase;

class BaseServiceTest extends TestCase
{
    public function test_successful_call_decodes_the_transport_response(): void
    {
        $service = new FixtureService('http://fixture.test', 5, json_encode(['status' => 'ok', 'quantity' => 3]));

        $result = $service->callGet('/stock/1');

        $this->assertSame('ok', $result['status']);
        $this->assertSame(3, $result['quantity']);
        $this->assertSame('http://fixture.test/stock/1', $service->capturedUrl);
    }

    public function test_transport_returning_false_produces_down(): void
    {
        $service = new FixtureService('http://fixture.test', 5, false);

        $result = $service->callGet('/stock/1');

        $this->assertSame('down', $result['status']);
        $this->assertSame(FixtureService::class, $result['service']);
        $this->assertStringContainsString('Could not reach', $result['reason']);
    }

    public function test_invalid_json_from_transport_produces_down(): void
    {
        $service = new FixtureService('http://fixture.test', 5, 'not-json{{{');

        $result = $service->callGet('/stock/1');

        $this->assertSame('down', $result['status']);
        $this->assertStringContainsString('Invalid JSON response', $result['reason']);
    }

    public function test_default_timeout_is_five_seconds_and_is_passed_to_the_stream_context(): void
    {
        $service = new FixtureService('http://fixture.test', 5, '{}');
        $service->callGet('/x');

        $this->assertSame(5, $service->capturedContextOptions['http']['timeout']);
    }

    public function test_overridden_timeout_is_honored_in_the_stream_context(): void
    {
        $service = new FixtureService('http://fixture.test', 2, '{}');
        $service->callGet('/x');

        $this->assertSame(2, $service->capturedContextOptions['http']['timeout']);
    }

    public function test_base_url_and_endpoint_are_joined_with_exactly_one_slash(): void
    {
        $service = new FixtureService('http://fixture.test/api/', 5, '{}');
        $service->callGet('/stock/1');

        $this->assertSame('http://fixture.test/api/stock/1', $service->capturedUrl);
    }

    public function test_ok_degraded_down_response_shapes(): void
    {
        $service = new FixtureService('http://fixture.test', 5, '{}');

        $this->assertSame(
            ['status' => 'ok', 'service' => FixtureService::class, 'dependencies' => ['stock' => 'ok']],
            $service->callOk(['stock' => 'ok']),
        );

        $this->assertSame(
            [
                'status'       => 'degraded',
                'service'      => FixtureService::class,
                'reason'       => 'Out of stock.',
                'dependencies' => ['stock' => 'degraded'],
            ],
            $service->callDegraded('Out of stock.', ['stock' => 'degraded']),
        );

        $this->assertSame(
            ['status' => 'down', 'service' => FixtureService::class, 'reason' => 'Unreachable.'],
            $service->callDown('Unreachable.'),
        );
    }
}
