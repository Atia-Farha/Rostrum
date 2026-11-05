<?php

namespace App\Policies;

use App\Models\Debate;
use App\Models\User;

/**
 * Authorization rules for debates.
 *
 * Every debate belongs to exactly one owner — the signed-in account that
 * created it (debate.user_id). Ownership is the only way to reach a debate.
 *
 * There is no shared sample. The seeded demo debate is owned by the demo
 * account like any other, so an account's record is a record of that
 * account's own practice and nobody else's. A newly registered account
 * starts with an empty record and fills it by debating.
 */
class DebatePolicy
{
    /**
     * Does the user own this debate?
     */
    private function owns(User $user, Debate $debate): bool
    {
        return $debate->user_id === $user->id;
    }

    /**
     * Can the user view the debate (arena, transcript, report)?
     */
    public function view(?User $user, Debate $debate): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->owns($user, $debate);
    }

    /**
     * Can the user play a turn in the debate?
     */
    public function participate(?User $user, Debate $debate): bool
    {
        return $user !== null && $this->owns($user, $debate);
    }

    /**
     * Can the user request adjudication for the debate?
     */
    public function adjudicate(?User $user, Debate $debate): bool
    {
        return $user !== null && $this->owns($user, $debate);
    }

    /**
     * Can the user rewrite one of the debate's turns?
     */
    public function rewrite(?User $user, Debate $debate): bool
    {
        return $user !== null && $this->owns($user, $debate);
    }

    /**
     * Can the user delete the debate (and its transcript and report)?
     */
    public function delete(?User $user, Debate $debate): bool
    {
        return $user !== null && $this->owns($user, $debate);
    }
}
