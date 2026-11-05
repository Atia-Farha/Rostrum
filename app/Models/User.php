<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Debates owned by this account.
     *
     * Guest debates (those created before signing in) are claimed by
     * AuthController::claimSessionDebates(), so they appear here too.
     */
    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class);
    }

    /**
     * Two-letter initials used by the nav avatar and account page.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return strtoupper(substr($this->email, 0, 1));
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
    }
}
