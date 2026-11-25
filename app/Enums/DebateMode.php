<?php

declare(strict_types=1);

namespace App\Enums;

enum DebateMode: string
{
    case Tournament = 'tournament';
    case Sparring = 'sparring';

    /**
     * Whether the mode has fixed phases (opening, rebuttal, closing).
     */
    public function hasFixedPhases(): bool
    {
        return $this === self::Tournament;
    }
}
