<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Http\RawResponse;
use PHPUnit\Framework\TestCase;

class RawResponseTest extends TestCase
{
    public function test_send_emits_the_body_verbatim_without_json_encoding(): void
    {
        $response = new RawResponse('%PDF-1.7 not real bytes', 200);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('%PDF-1.7 not real bytes', $output);
    }

    public function test_send_emits_no_body_for_204(): void
    {
        $response = new RawResponse('should not appear', 204);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_send_emits_no_body_for_a_null_body_regardless_of_status(): void
    {
        $response = new RawResponse(null, 200);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_with_header_still_returns_a_raw_response_after_cloning(): void
    {
        $original = new RawResponse('body', 200);
        $withHeader = $original->withHeader('Content-Type', 'application/pdf');

        $this->assertInstanceOf(RawResponse::class, $withHeader);
        $this->assertNotSame($original, $withHeader);
    }
}
