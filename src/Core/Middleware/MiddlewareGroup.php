<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use LogicException;

final class MiddlewareGroup
{
    private function __construct(
        public readonly string $name,
        public readonly array  $links = [], // MiddlewareLink[]
    ) {}

    public static function define(string $name, array $links): static
    {
        $validated = [];
        $seen      = [];

        foreach ($links as $link) {
            $link = $link instanceof MiddlewareLink
                ? $link
                : new MiddlewareLink($link);

            if (!$link->canHaveGroup) {
                throw new LogicException(
                    "Middleware [{$link->middleware}] has canHaveGroup=false and cannot be added to group [{$name}]."
                );
            }

            if (in_array($link->middleware, $seen, strict: true)) {
                $allowsDuplicates = is_a($link->middleware, DuplicableInterface::class, allow_string: true)
                    && $link->middleware::allowDuplicates();

                if (!$allowsDuplicates) {
                    throw new LogicException(
                        "Duplicate middleware [{$link->middleware}] detected in group [{$name}]. " .
                        "Implement DuplicableInterface and return true from allowDuplicates() to allow this explicitly."
                    );
                }
            }

            $seen[]      = $link->middleware;
            $validated[] = $link;
        }

        return new static($name, $validated);
    }

    public static function ref(string $name): static
    {
        return new static($name);
    }

    public function isRef(): bool
    {
        return $this->links === [];
    }
}