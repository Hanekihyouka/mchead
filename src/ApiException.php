<?php

declare(strict_types=1);

namespace McSkinRender;

/**
 * An error that maps directly to an HTTP status for the API client.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
