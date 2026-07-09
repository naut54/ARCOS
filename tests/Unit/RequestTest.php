<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function test_accessors_return_constructor_values(): void
    {
        $request = new Request(
            'GET',
            '/products',
            ['Authorization' => 'Bearer x'],
            ['id' => '1'],
            ['name' => 'Widget'],
        );

        $this->assertSame('GET', $request->method());
        $this->assertSame('/products', $request->uri());
        $this->assertSame(['Authorization' => 'Bearer x'], $request->headers());
        $this->assertSame('Bearer x', $request->header('Authorization'));
        $this->assertNull($request->header('Missing'));
        $this->assertSame(['id' => '1'], $request->query());
        $this->assertSame(['name' => 'Widget'], $request->body());
    }

    public function test_input_prefers_body_over_query_and_falls_back_to_default(): void
    {
        $request = new Request('GET', '/x', [], ['id' => 'from-query'], ['id' => 'from-body']);

        $this->assertSame('from-body', $request->input('id'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    public function test_input_reads_from_query_when_absent_from_body(): void
    {
        $request = new Request('GET', '/x', [], ['id' => 'from-query'], []);

        $this->assertSame('from-query', $request->input('id'));
    }

    // parseBody() — pure function, independent of superglobals

    public function test_parse_body_decodes_json_regardless_of_verb(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $result = Request::parseBody(
                $method,
                'application/json',
                fn() => '{"name":"Widget"}',
                [],
            );

            $this->assertSame(['name' => 'Widget'], $result, "for method {$method}");
        }
    }

    public function test_parse_body_reads_form_urlencoded_from_raw_stream_for_put(): void
    {
        $result = Request::parseBody(
            'PUT',
            'application/x-www-form-urlencoded',
            fn() => 'name=Widget&price=9.99',
            [],
        );

        $this->assertSame(['name' => 'Widget', 'price' => '9.99'], $result);
    }

    public function test_parse_body_reads_form_urlencoded_from_raw_stream_for_patch(): void
    {
        $result = Request::parseBody(
            'PATCH',
            'application/x-www-form-urlencoded',
            fn() => 'name=Widget',
            [],
        );

        $this->assertSame(['name' => 'Widget'], $result);
    }

    public function test_parse_body_reads_form_urlencoded_from_raw_stream_for_delete(): void
    {
        $result = Request::parseBody(
            'DELETE',
            'application/x-www-form-urlencoded',
            fn() => 'reason=cleanup',
            [],
        );

        $this->assertSame(['reason' => 'cleanup'], $result);
    }

    public function test_parse_body_uses_post_superglobal_for_form_urlencoded_post(): void
    {
        $rawInputCalled = false;

        $result = Request::parseBody(
            'POST',
            'application/x-www-form-urlencoded',
            function () use (&$rawInputCalled) {
                $rawInputCalled = true;
                return 'should-not-be-used=1';
            },
            ['name' => 'Widget'],
        );

        $this->assertSame(['name' => 'Widget'], $result);
        $this->assertFalse($rawInputCalled, 'php://input should not be read for a plain POST');
    }

    public function test_parse_body_falls_back_to_post_for_unrecognized_content_type(): void
    {
        $result = Request::parseBody(
            'PUT',
            'text/plain',
            fn() => 'irrelevant',
            ['fallback' => 'value'],
        );

        $this->assertSame(['fallback' => 'value'], $result);
    }
}
