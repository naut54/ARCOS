<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Services\BaseService;

/**
 * A BaseService subclass whose transport() is overridden with a canned
 * response, so Unit tests exercise the request-building/response-decoding
 * logic without ever touching the network.
 */
class FixtureService extends BaseService
{
    public ?string $capturedUrl = null;
    public ?array  $capturedContextOptions = null;

    public function __construct(
        string $baseUrl,
        int $timeout,
        private readonly string|false $transportResult,
    ) {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
    }

    protected function transport(string $url, $context): string|false
    {
        $this->capturedUrl = $url;
        $this->capturedContextOptions = stream_context_get_options($context);

        return $this->transportResult;
    }

    public function callGet(string $endpoint, array $headers = []): array
    {
        return $this->get($endpoint, $headers);
    }

    public function callOk(array $dependencies = []): array
    {
        return $this->ok($dependencies);
    }

    public function callDegraded(string $reason, array $dependencies = []): array
    {
        return $this->degraded($reason, $dependencies);
    }

    public function callDown(string $reason): array
    {
        return $this->down($reason);
    }

    public function health(): array
    {
        return $this->ok();
    }
}
