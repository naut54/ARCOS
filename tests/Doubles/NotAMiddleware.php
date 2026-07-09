<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

/**
 * Deliberately does not implement MiddlewareInterface, for negative tests
 * against MiddlewareLink's construction-time validation.
 */
class NotAMiddleware
{
}
