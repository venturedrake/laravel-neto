<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Resources;

use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Neto;

abstract class Resource
{
    public function __construct(
        protected readonly Neto $client,
    ) {}

    /**
     * The underlying API client.
     */
    public function client(): Neto
    {
        return $this->client;
    }

    /**
     * Extract the records under the given key from an API response.
     *
     * Neto returns a list of records for most actions, but a single record
     * is sometimes returned as a bare object and an empty result may omit
     * the key entirely. Normalise all of those into a list.
     *
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function records(array $response, string $key): array
    {
        $records = $response[$key] ?? [];

        if (! is_array($records) || $records === []) {
            return [];
        }

        if (! array_is_list($records)) {
            $records = [$records];
        }

        $normalised = [];

        foreach ($records as $record) {
            if (is_array($record)) {
                /** @var array<string, mixed> $record */
                $normalised[] = $record;
            }
        }

        return $normalised;
    }

    /**
     * Extract exactly one record from an API response, or fail.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     */
    protected function firstRecord(array $response, string $key, string $identifier): array
    {
        $records = $this->records($response, $key);

        if ($records === []) {
            throw NotFoundException::for($key, $identifier);
        }

        return $records[0];
    }

    /**
     * Wrap a single record into the list shape Neto expects for Add/Update actions.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, list<array<string, mixed>>>
     */
    protected function wrap(string $key, array $record): array
    {
        return [$key => [$record]];
    }
}
