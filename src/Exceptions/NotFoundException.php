<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Exceptions;

/**
 * Thrown when a single record was requested but the Neto API did not
 * return one, for example an order ID that does not exist.
 */
class NotFoundException extends NetoException
{
    public static function for(string $resource, string $identifier): self
    {
        return new self(sprintf('%s "%s" was not found in Neto.', $resource, $identifier));
    }
}
