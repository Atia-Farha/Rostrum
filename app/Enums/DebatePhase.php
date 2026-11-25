<?php

declare(strict_types=1);

namespace App\Enums;

enum DebatePhase: string
{
    case Opening = 'opening';
    case Rebuttal = 'rebuttal';
    case Closing = 'closing';

    /**
     * Whether this phase comes before another in the round order.
     */
    public function isBefore(self $other): bool
    {
        $order = [self::Opening->value => 1, self::Rebuttal->value => 2, self::Closing->value => 3];

        return $order[$this->value] < $order[$other->value];
    }

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Rebuttal => 'Rebuttal',
            self::Closing => 'Closing',
        };
    }
}
