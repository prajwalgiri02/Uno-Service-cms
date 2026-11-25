<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id', 'category_id', 'name', 'phone', 'area', 'landmark', 'latitude', 'longitude', 'opening_hours',
    'blurb', 'vibes', 'women_run', 'status', 'status_note', 'check_photos', 'check_pin', 'check_phone', 'check_prices',
    'reviewed_by', 'reviewed_at', 'submitted_at', 'auto_paused_at', 'check_in_code', 'open_today_on', 'open_confirmed_at', 'no_wait_until',
])]
class Shop extends Model
{
    use HasFactory;

    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'opening_hours' => 'array',
            'vibes' => 'array',
            'women_run' => 'boolean',
            'status' => ShopStatus::class,
            'check_photos' => 'boolean',
            'check_pin' => 'boolean',
            'check_phone' => 'boolean',
            'check_prices' => 'boolean',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_paused_at' => 'datetime',
            'open_today_on' => 'date',
            'open_confirmed_at' => 'datetime',
            'no_wait_until' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ShopPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ShopService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function currentOffer(): HasOne
    {
        return $this->hasOne(Offer::class)
            ->whereNull('stopped_at')
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->latestOfMany();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function nudges(): BelongsToMany
    {
        return $this->belongsToMany(Nudge::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShopStatusLog::class)->latest();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ShopStatus::APPROVED->value);
    }

    public function isLive(): bool
    {
        return $this->status === ShopStatus::APPROVED;
    }

    public function isOpenToday(): bool
    {
        return $this->open_today_on?->isToday() ?? false;
    }

    public function noWaitActive(): bool
    {
        return $this->no_wait_until?->isFuture() ?? false;
    }

    /**
     * @return array{open: string, close: string}|null
     */
    public function hoursOn(Carbon $day): ?array
    {
        $hours = $this->opening_hours[self::WEEKDAYS[$day->dayOfWeekIso - 1]] ?? null;

        return is_array($hours) && isset($hours['open'], $hours['close']) ? $hours : null;
    }

    public function isWithinHours(?Carbon $at = null): bool
    {
        $at ??= now();

        if (empty($this->opening_hours)) {
            return true;
        }

        $hours = $this->hoursOn($at);

        if ($hours === null) {
            return false;
        }

        $time = $at->format('H:i');

        return $time >= $hours['open'] && $time < $hours['close'];
    }

    public function openTill(): ?string
    {
        $hours = $this->hoursOn(now());

        return $hours ? Carbon::createFromFormat('H:i', $hours['close'])->format('g:i a') : null;
    }

    public function priceMin(): ?int
    {
        return $this->relationLoaded('services') ? $this->services->min('price_min') : $this->services()->min('price_min');
    }

    public function priceMax(): ?int
    {
        return $this->relationLoaded('services') ? $this->services->max('price_max') : $this->services()->max('price_max');
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));
    }
}
