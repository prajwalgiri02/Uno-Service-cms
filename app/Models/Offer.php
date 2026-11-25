<?php

namespace App\Models;

use App\Support\Week;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['shop_id', 'percent', 'weekdays', 'from_time', 'to_time', 'starts_on', 'ends_on', 'stopped_at'])]
class Offer extends Model
{
    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'stopped_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isRunningAt(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->stopped_at !== null || $at->lt($this->starts_on->startOfDay()) || $at->gt($this->ends_on->endOfDay())) {
            return false;
        }

        $time = $at->format('H:i:s');

        return in_array($at->dayOfWeekIso, $this->weekdays, true)
            && $time >= $this->from_time
            && $time < $this->to_time;
    }

    public function label(): string
    {
        $from = Carbon::createFromFormat('H:i:s', $this->from_time);
        $to = Carbon::createFromFormat('H:i:s', $this->to_time);

        $days = Week::isWorkdays($this->weekdays) ? 'weekdays' : implode(', ', array_map(
            fn (int $d) => Carbon::now()->startOfWeek()->addDays($d - 1)->format('D'),
            $this->weekdays,
        ));

        return "{$this->percent}% off · {$days} {$from->format('g')}–{$to->format('g a')}";
    }
}
