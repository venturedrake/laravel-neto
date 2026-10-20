<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Thrown when a request never produced a usable Neto API response: a
 * connection failure, a non-2xx HTTP status, or a body that is not JSON.
 */
class RequestException extends NetoException
{
    public function __construct(
        string $message,
        public readonly string $action,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    public static function fromResponse(string $action, Response $response): self
    {
        return new self(
            sprintf('Neto API request "%s" failed with HTTP status %d.', $action, $response->status()),
            $action,
            $response,
        );
    }

    public static function invalidJson(string $action, Response $response): self
    {
        return new self(
            sprintf('Neto API request "%s" returned a response that could not be decoded as JSON.', $action),
            $action,
            $response,
        );
    }

    public static function connection(string $action, Throwable $previous): self
    {
        return new self(
            sprintf('Neto API request "%s" could not be completed: %s', $action, $previous->getMessage()),
            $action,
            null,
            $previous,
        );
    }
}
