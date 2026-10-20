<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Exceptions;

/**
 * Thrown when the package has not been configured with the values it needs
 * to talk to the Neto API.
 */
class ConfigurationException extends NetoException
{
    public static function missing(string $key): self
    {
        return new self(sprintf(
            'The Neto API "%s" has not been configured. Set it in config/neto.php or the matching environment variable.',
            $key,
        ));
    }
}
