<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use LogicException;

final class MiddlewareLink
{
    /**
     * Maps a link's declared `name` to its middleware class, so that
     * ref() can resolve a name back to the class it was registered for.
     *
     * Static, like SkipRegistry (see arcos-principles.md, Deviation 3): ARCOS
     * runs one PHP process per request, so this starts empty on every request
     * and is populated when config/middleware.php runs, before dispatch. Test
     * suites must call resetNamedRegistry() in teardown for isolation between
     * test cases within the same process.
     */
    private static array $namedLinks = []; // name => middleware class

    public function __construct(
        public readonly string  $middleware,
        public readonly bool    $isMandatory  = false,
        public readonly bool    $isSkippable  = false,
        public readonly bool    $canHaveGroup = true,
        public readonly ?string $name         = null,
    ) {
        if (!is_a($this->middleware, MiddlewareInterface::class, allow_string: true)) {
            throw new LogicException(
                "[{$this->middleware}] must implement MiddlewareInterface to be used as a MiddlewareLink."
            );
        }

        if ($this->isMandatory && !is_a($this->middleware, MandatoryMiddlewareInterface::class, allow_string: true)) {
            throw new LogicException(
                "[{$this->middleware}] is declared as mandatory but does not implement MandatoryMiddlewareInterface."
            );
        }

        if ($this->isMandatory && $this->isSkippable) {
            throw new LogicException(
                "[{$this->middleware}] cannot be both mandatory and skippable. These attributes are mutually exclusive."
            );
        }

        if ($this->name !== null) {
            if (isset(self::$namedLinks[$this->name]) && self::$namedLinks[$this->name] !== $this->middleware) {
                throw new LogicException(
                    "Middleware name [{$this->name}] is already registered for [" .
                    self::$namedLinks[$this->name] . "]. Link names must be unique."
                );
            }

            self::$namedLinks[$this->name] = $this->middleware;
        }
    }

    /**
     * Resolves a reference for use with SkipRegistry::skip(). Accepts either
     * a middleware class name, or the `name` a MiddlewareLink was declared
     * with — whichever was registered first for that name wins.
     */
    public static function ref(string $reference): static
    {
        $middlewareClass = self::$namedLinks[$reference] ?? $reference;

        return new static($middlewareClass);
    }

    public static function resetNamedRegistry(): void
    {
        self::$namedLinks = [];
    }
}