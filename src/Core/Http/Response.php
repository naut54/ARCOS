<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

class Response
{
    private array $headers = [];

    public function __construct(
        private readonly mixed $body,
        private readonly int   $status = 200,
    ) {}

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): mixed
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // A 204 (or an explicit null body) must not emit a response body.
        if ($this->status === 204 || $this->body === null) {
            return;
        }

        header('Content-Type: application/json');

        echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}