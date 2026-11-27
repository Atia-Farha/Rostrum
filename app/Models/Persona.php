<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Persona extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'description',
        'system_prompt',
    ];

    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class);
    }
}
