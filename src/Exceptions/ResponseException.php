<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Exceptions;

/**
 * Thrown when the Neto API answered the request but reported an error,
 * i.e. the response "Ack" is "Error" or it carries error messages.
 */
class ResponseException extends NetoException
{
    /**
     * @param  list<array{Message: string, SeverityCode: string|null, Description: string|null}>  $errors
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        string $message,
        public readonly string $action,
        public readonly array $errors = [],
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(string $action, array $response): self
    {
        $errors = self::normaliseMessages($response['Messages']['Error'] ?? []);

        $summary = $errors === []
            ? 'the API did not return an error message'
            : implode('; ', array_map(
                fn (array $error): string => $error['Description'] !== null && $error['Description'] !== ''
                    ? sprintf('%s (%s)', $error['Message'], $error['Description'])
                    : $error['Message'],
                $errors,
            ));

        return new self(
            sprintf('Neto API request "%s" returned an error: %s', $action, $summary),
            $action,
            $errors,
            $response,
        );
    }

    /**
     * The first error message reported by the API, if any.
     */
    public function firstError(): ?string
    {
        return $this->errors[0]['Message'] ?? null;
    }

    /**
     * Neto returns a single message as an object and several messages as a
     * list of objects. Normalise both shapes into a list.
     *
     * @return list<array{Message: string, SeverityCode: string|null, Description: string|null}>
     */
    public static function normaliseMessages(mixed $messages): array
    {
        if (! is_array($messages) || $messages === []) {
            return [];
        }

        if (array_key_exists('Message', $messages)) {
            $messages = [$messages];
        }

        $normalised = [];

        foreach ($messages as $message) {
            if (is_string($message)) {
                $normalised[] = ['Message' => $message, 'SeverityCode' => null, 'Description' => null];

                continue;
            }

            if (! is_array($message)) {
                continue;
            }

            $text = $message['Message'] ?? null;

            $normalised[] = [
                'Message' => is_scalar($text) ? (string) $text : 'Unknown error',
                'SeverityCode' => isset($message['SeverityCode']) && is_scalar($message['SeverityCode']) ? (string) $message['SeverityCode'] : null,
                'Description' => isset($message['Description']) && is_scalar($message['Description']) ? (string) $message['Description'] : null,
            ];
        }

        return $normalised;
    }
}
