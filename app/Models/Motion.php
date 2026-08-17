<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motion extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'text_en',
        'text_bn',
        'category',
        'source',
    ];

    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class);
    }

    /**
     * Returns the motion text for the given locale, falling back to the other.
     */
    public function textFor(string $lang): string
    {
        return $lang === 'bn'
            ? ($this->text_bn ?: $this->text_en ?? '')
            : ($this->text_en ?: $this->text_bn ?? '');
    }
}
