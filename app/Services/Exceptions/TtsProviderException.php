<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

final class TtsProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $shouldFallback, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
