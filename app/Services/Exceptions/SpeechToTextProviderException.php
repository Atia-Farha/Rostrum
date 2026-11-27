<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

final class SpeechToTextProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $shouldFallback)
    {
        parent::__construct($message);
    }
}
