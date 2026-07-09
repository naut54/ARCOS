<?php

declare(strict_types=1);

namespace Arcos\Tests\Fixtures;

use Arcos\Core\Helpers\ErrorHelper;
use PHPUnit\Framework\TestCase;

/**
 * Baseline snapshot of the error catalog's exact wording and status codes.
 * Per the testing philosophy in docs/arcos-guidelines.md §10, a Fixtures-tier
 * test failure signals unintentional drift from a known baseline — it is not
 * meant to block the build, just to make incidental catalog changes visible.
 */
class ErrorCatalogSnapshotTest extends TestCase
{
    public function test_error_catalog_matches_the_known_baseline(): void
    {
        $expected = [
            'RTE-001' => ['status' => 404, 'error_level' => 'Low',  'message' => 'The requested resource was not found.'],
            'RTE-002' => ['status' => 405, 'error_level' => 'Low',  'message' => 'The request method is not allowed for this route.'],
            'VAL-001' => ['status' => 422, 'error_level' => 'Low',  'message' => 'The request is missing required fields.'],
            'VAL-002' => ['status' => 422, 'error_level' => 'Low',  'message' => 'The provided data failed validation.'],
            'RES-001' => ['status' => 404, 'error_level' => 'Low',  'message' => 'The requested resource does not exist.'],
            'SYS-001' => ['status' => 500, 'error_level' => 'High', 'message' => 'An unexpected error occurred.'],
            'SYS-002' => ['status' => 503, 'error_level' => 'High', 'message' => 'A required service is currently unavailable.'],
        ];

        foreach ($expected as $code => $baseline) {
            $body = ErrorHelper::respond($code)->body();

            $this->assertSame($baseline['status'], ErrorHelper::respond($code)->status(), "status drifted for {$code}");
            $this->assertSame($baseline['error_level'], $body['error_level'], "error_level drifted for {$code}");
            $this->assertSame($baseline['message'], $body['message'], "message wording drifted for {$code}");
        }
    }
}
