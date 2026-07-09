<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

/**
 * Shared trace log for onion-order assertions across the layer-specific
 * tracer middlewares (GlobalTraceMiddleware, GroupTraceMiddleware,
 * PerRouteTraceMiddleware). Kept separate from any one middleware class so
 * the same log can record entries from middleware living in different
 * chain layers.
 */
class TraceLog
{
    private static array $entries = [];

    public static function push(string $entry): void
    {
        self::$entries[] = $entry;
    }

    public static function all(): array
    {
        return self::$entries;
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
