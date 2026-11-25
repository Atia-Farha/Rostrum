<?php

declare(strict_types=1);

namespace App\Enums;

enum DebateStatus: string
{
    case InProgress = 'in_progress';
    case Adjudicated = 'adjudicated';

    /**
     * Whether this status means the debate still has active turns to play.
     */
    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Whether the debate has a completed adjudication report.
     */
    public function isComplete(): bool
    {
        return $this === self::Adjudicated;
    }
}
