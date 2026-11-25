<?php

declare(strict_types=1);

namespace App\Enums;

enum DebateSide: string
{
    case Government = 'government';
    case Opposition = 'opposition';

    /**
     * Returns the opposite side.
     */
    public function opposite(): self
    {
        return match ($this) {
            self::Government => self::Opposition,
            self::Opposition => self::Government,
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Opposition => 'Opposition',
        };
    }
}
