<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'category_id', 'title', 'variant', 'max_price', 'radius_m', 'only_no_wait', 'offers_only', 'women_run',
    'active', 'paused_note', 'expires_at', 'kept_at', 'done_at',
])]
class Errand extends Model
{
    use HasFactory;

    public const STALE_AFTER_DAYS = 14;

    protected function casts(): array
    {
        return [
            'only_no_wait' => 'boolean',
            'offers_only' => 'boolean',
            'women_run' => 'boolean',
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'kept_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Still on the list: not done and not expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('done_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Open and switched on, so it can trigger nudges.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWatching(Builder $query): void
    {
        $query->open()->where('active', true);
    }

    public function isStale(): bool
    {
        return ($this->kept_at ?? $this->created_at)->lte(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
