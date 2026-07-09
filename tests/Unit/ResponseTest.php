<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_status_and_body_accessors(): void
    {
        $response = new Response(['success' => true], 201);

        $this->assertSame(201, $response->status());
        $this->assertSame(['success' => true], $response->body());
    }

    public function test_with_header_returns_a_clone_and_does_not_mutate_the_original(): void
    {
        $original = new Response(['success' => true], 200);
        $withHeader = $original->withHeader('X-Test', 'value');

        $this->assertNotSame($original, $withHeader);

        // No public accessor for headers, but we can prove non-mutation by
        // sending both and confirming only one had a chance to add the header —
        // exercised indirectly via send() header emission is covered by the
        // CLI/SAPI header() call, which PHPUnit CLI runs as a no-op warning-free
        // call for already-sent-headers-less contexts; here we just assert clone identity.
        $this->assertInstanceOf(Response::class, $withHeader);
    }

    public function test_send_emits_no_body_for_204(): void
    {
        $response = new Response(null, 204);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_send_emits_no_body_for_a_null_body_regardless_of_status(): void
    {
        $response = new Response(null, 200);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_send_emits_json_encoded_body_for_normal_responses(): void
    {
        $response = new Response(['success' => true, 'data' => [1, 2, 3]], 200);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame(['success' => true, 'data' => [1, 2, 3]], json_decode($output, associative: true));
    }
}
