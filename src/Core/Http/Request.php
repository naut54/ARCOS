<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $headers,
        private readonly array  $query,
        private readonly array  $body,
    ) {}

    public static function fromGlobals(UriResolverInterface $uriResolver): static
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return new static(
            method:  $method,
            uri:     $uriResolver->resolve(),
            headers: getallheaders() ?: [],
            query:   $_GET,
            body:    self::parseBody(
                $method,
                $_SERVER['CONTENT_TYPE'] ?? '',
                fn() => file_get_contents('php://input'),
                $_POST,
            ),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Pure body-parsing logic, independent of superglobals so it can be
     * unit-tested directly. $rawInput is lazy — only called if actually needed —
     * so callers that don't need it (e.g. a plain POST) never touch php://input.
     */
    public static function parseBody(string $method, string $contentType, callable $rawInput, array $post): array
    {
        if (str_contains($contentType, 'application/json')) {
            return json_decode($rawInput(), associative: true) ?? [];
        }

        // PHP only populates $_POST for POST requests. PUT/PATCH/DELETE with a
        // form-urlencoded body must be parsed from the raw stream directly.
        if ($method !== 'POST' && str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($rawInput(), $parsed);
            return $parsed;
        }

        return $post;
    }
}