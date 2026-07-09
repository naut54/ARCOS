<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Helpers\ResponseHelper;
use PHPUnit\Framework\TestCase;

class ResponseHelperTest extends TestCase
{
    public function test_ok_returns_200_with_data(): void
    {
        $response = ResponseHelper::ok([['id' => 1]]);

        $this->assertSame(200, $response->status());
        $this->assertSame(['success' => true, 'data' => [['id' => 1]]], $response->body());
    }

    public function test_created_returns_201_with_data(): void
    {
        $response = ResponseHelper::created([['id' => 1]]);

        $this->assertSame(201, $response->status());
        $this->assertSame(['success' => true, 'data' => [['id' => 1]]], $response->body());
    }

    public function test_no_content_returns_204_with_null_body(): void
    {
        $response = ResponseHelper::noContent();

        $this->assertSame(204, $response->status());
        $this->assertNull($response->body());
    }

    public function test_message_returns_200_by_default(): void
    {
        $response = ResponseHelper::message('Deleted successfully.');

        $this->assertSame(200, $response->status());
        $this->assertSame(['success' => true, 'message' => 'Deleted successfully.'], $response->body());
    }

    public function test_message_accepts_a_custom_status(): void
    {
        $response = ResponseHelper::message('Accepted.', 202);

        $this->assertSame(202, $response->status());
    }
}
