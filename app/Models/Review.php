<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['check_in_id', 'user_id', 'shop_id', 'stars', 'text', 'reply', 'replied_at'])]
class Review extends Model
{
    public const LOW_STARS = 2;

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }

    public function needsLook(): bool
    {
        return $this->stars <= self::LOW_STARS && $this->reply === null;
    }
}
