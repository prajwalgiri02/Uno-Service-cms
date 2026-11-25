<?php

namespace App\Models;

use App\Support\Week;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'preset', 'title', 'weekdays', 'from_time', 'to_time', 'enabled'])]
class FreeWindow extends Model
{
    /**
     * Presets shown in the app (weekday numbers are ISO: 1 = Monday … 7 = Sunday).
     *
     * @var array<string, array{title: string, emoji: string, weekdays: list<int>, from: string, to: string, enabled: bool}>
     */
    public const PRESETS = [
        'after_work' => ['title' => 'after work', 'emoji' => '🌇', 'weekdays' => [1, 2, 3, 4, 5, 7], 'from' => '17:00', 'to' => '19:00', 'enabled' => true],
        'lunch' => ['title' => 'lunch break', 'emoji' => '🥟', 'weekdays' => [1, 2, 3, 4, 5, 7], 'from' => '13:00', 'to' => '14:00', 'enabled' => false],
        'saturday' => ['title' => 'saturday', 'emoji' => '☀️', 'weekdays' => [6], 'from' => '09:00', 'to' => '13:00', 'enabled' => true],
        'late' => ['title' => 'late evening', 'emoji' => '🌙', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'from' => '19:00', 'to' => '20:00', 'enabled' => false],
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function covers(Carbon $at): bool
    {
        $time = $at->format('H:i:s');

        return $this->enabled
            && in_array($at->dayOfWeekIso, $this->weekdays, true)
            && $time >= $this->from_time
            && $time < $this->to_time;
    }

    public function subtitle(): string
    {
        $from = Carbon::createFromFormat('H:i:s', $this->from_time);
        $to = Carbon::createFromFormat('H:i:s', $this->to_time);
        $range = $from->format('a') === $to->format('a')
            ? $from->format('g').'–'.$to->format('g a')
            : $from->format('g a').' – '.$to->format('g a');

        $days = match (true) {
            count($this->weekdays) === 7 => 'any day',
            $this->weekdays == [6] => '',
            Week::isWorkdays($this->weekdays) => 'weekdays',
            default => implode(', ', array_map(fn (int $d) => Carbon::now()->startOfWeek()->addDays($d - 1)->format('D'), $this->weekdays)),
        };

        return trim("{$days} {$range}");
    }
}
