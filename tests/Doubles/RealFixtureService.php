<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Services\BaseService;

/**
 * A BaseService subclass that does NOT override transport() — used by Support
 * tests to exercise the real file_get_contents-based HTTP path end-to-end
 * against a real (local) server.
 */
class RealFixtureService extends BaseService
{
    public function __construct(string $baseUrl, int $timeout = 5)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
    }

    public function callGet(string $endpoint): array
    {
        return $this->get($endpoint);
    }

    public function health(): array
    {
        return $this->ok();
    }
}
