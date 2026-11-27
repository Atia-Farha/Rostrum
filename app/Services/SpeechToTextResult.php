<?php

declare(strict_types=1);

namespace App\Services;

final readonly class SpeechToTextResult
{
    public function __construct(
        public string $text,
        public string $provider,
    ) {}
}
