<?php

namespace App\Models;

use App\Enums\NudgeResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'shop_id', 'shop_ids', 'errand_ids', 'distance_m', 'title', 'body', 'result', 'push_delivered', 'opened_at', 'visited_at'])]
class Nudge extends Model
{
    protected function casts(): array
    {
        return [
            'shop_ids' => 'array',
            'errand_ids' => 'array',
            'result' => NudgeResult::class,
            'push_delivered' => 'boolean',
            'opened_at' => 'datetime',
            'visited_at' => 'datetime',
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

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class);
    }

    public function isCombined(): bool
    {
        return count($this->shop_ids) > 1;
    }
}
