<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

/**
 * Escape hatch for the single-JSON-output-point contract that
 * Response::send() otherwise enforces (see arcos-principles.md) -- for
 * the rare response that genuinely isn't JSON (a binary file download,
 * for instance). $body is emitted verbatim instead of being
 * json_encode()'d; callers are responsible for setting their own
 * Content-Type (and any other headers) via the inherited withHeader().
 *
 * Deliberately just a send() override, not a parallel constructor or
 * body/status representation -- everything else about Response (status
 * codes, the 204/null-body short-circuit, withHeader()'s clone-based
 * immutability) still applies unchanged.
 */
class RawResponse extends Response
{
    public function send(): void
    {
        http_response_code($this->status());

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Same short-circuit as the parent -- a 204 or an explicit null
        // body must not emit a response body.
        if ($this->status() === 204 || $this->body() === null) {
            return;
        }

        echo $this->body();
    }
}
