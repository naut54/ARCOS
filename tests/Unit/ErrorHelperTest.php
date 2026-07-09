<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Helpers\ErrorHelper;
use PHPUnit\Framework\TestCase;

class ErrorHelperTest extends TestCase
{
    public function test_respond_returns_the_catalog_entry_for_a_known_code(): void
    {
        $response = ErrorHelper::respond('VAL-001');
        $body     = $response->body();

        $this->assertSame(422, $response->status());
        $this->assertFalse($body['success']);
        $this->assertSame('VAL-001', $body['error_code']);
        $this->assertSame('The request is missing required fields.', $body['message']);
        $this->assertArrayHasKey('suggested_action', $body);
        $this->assertArrayHasKey('error_level', $body);
    }

    public function test_respond_with_message_override_replaces_only_the_message(): void
    {
        $response = ErrorHelper::respond('VAL-001', 'The field "name" is required.');
        $body     = $response->body();

        $this->assertSame('The field "name" is required.', $body['message']);
        $this->assertSame(422, $response->status());
        $this->assertSame('The request is missing required fields.' !== $body['message'], true);
    }

    public function test_respond_falls_back_to_sys_001_for_an_unknown_code(): void
    {
        $response = ErrorHelper::respond('NOT-A-REAL-CODE');
        $body     = $response->body();

        $this->assertSame(500, $response->status());
        $this->assertSame('SYS-001', $body['error_code']);
    }

    public function test_every_documented_domain_has_at_least_one_code(): void
    {
        foreach (['RTE-001', 'RTE-002', 'VAL-001', 'VAL-002', 'RES-001', 'SYS-001', 'SYS-002'] as $code) {
            $response = ErrorHelper::respond($code);
            $this->assertSame($code, $response->body()['error_code'], "for code {$code}");
        }
    }
}
