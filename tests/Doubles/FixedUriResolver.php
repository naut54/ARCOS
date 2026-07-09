<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\UriResolverInterface;

class FixedUriResolver implements UriResolverInterface
{
    public function __construct(private readonly string $uri = '/')
    {
    }

    public function resolve(): string
    {
        return $this->uri;
    }
}
