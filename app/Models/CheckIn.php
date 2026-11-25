<?php

namespace App\Models;

use App\Enums\CheckInMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'shop_id', 'nudge_id', 'method', 'points', 'saved_rs', 'minutes_saved', 'first_visit'])]
class CheckIn extends Model
{
    protected function casts(): array
    {
        return [
            'method' => CheckInMethod::class,
            'first_visit' => 'boolean',
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

    public function nudge(): BelongsTo
    {
        return $this->belongsTo(Nudge::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
