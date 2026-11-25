<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Offer;
use App\Models\Shop;
use App\Support\Week;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OfferService
{
    /** Columns of the heatmap: 10 am to 6 pm. */
    public const HOURS = [10, 11, 12, 13, 14, 15, 16, 17, 18];

    /** Rows of the heatmap, Sunday first (ISO weekday numbers). */
    public const DAYS = [7, 1, 2, 3, 4, 5, 6];

    private const SLOT_HOURS = 2;

    /**
     * Check-ins over the last 4 weeks as levels 0 (quiet) to 3 (busy).
     *
     * @return array{days: list<int>, hours: list<int>, levels: list<list<int>>, suggestion: array{weekdays: list<int>, from: string, to: string, label: string}}
     */
    public function busyMap(Shop $shop): array
    {
        $counts = array_fill_keys(self::DAYS, array_fill_keys(self::HOURS, 0));

        $shop->checkIns()
            ->where('created_at', '>=', now()->subWeeks(4))
            ->pluck('created_at')
            ->each(function (Carbon $at) use (&$counts) {
                if (isset($counts[$at->dayOfWeekIso][$at->hour])) {
                    $counts[$at->dayOfWeekIso][$at->hour]++;
                }
            });

        $max = max(1, max(array_map('max', $counts)));

        $levels = array_map(
            fn (array $row) => array_values(array_map(fn (int $n) => $n === 0 ? 0 : (int) min(3, ceil($n / $max * 3)), $row)),
            array_values($counts),
        );

        return [
            'days' => self::DAYS,
            'hours' => self::HOURS,
            'levels' => $levels,
            'suggestion' => $this->suggestion($counts),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function start(Shop $shop, int $percent, array $weekdays, string $from, string $to): Offer
    {
        if (! $shop->isLive()) {
            throw ValidationException::withMessages(['percent' => 'Offers start once your shop is approved.']);
        }

        $this->stop($shop);

        return $shop->offers()->create([
            'percent' => $percent,
            'weekdays' => array_values(array_unique(array_map('intval', $weekdays))),
            'from_time' => Carbon::createFromFormat('H:i', $from)->format('H:i:s'),
            'to_time' => Carbon::createFromFormat('H:i', $to)->format('H:i:s'),
            'starts_on' => today(),
            'ends_on' => today()->endOfWeek(Carbon::SATURDAY),
        ]);
    }

    public function stop(Shop $shop): void
    {
        $shop->offers()->whereNull('stopped_at')->update(['stopped_at' => now()]);
    }

    /**
     * The quietest two-hour slot on workdays, defaulting to 2–4 pm.
     *
     * @param  array<int, array<int, int>>  $counts
     * @return array{weekdays: list<int>, from: string, to: string, label: string}
     */
    private function suggestion(array $counts): array
    {
        $best = 14;
        $bestTotal = PHP_INT_MAX;

        foreach (array_slice(self::HOURS, 0, -self::SLOT_HOURS + 1) as $hour) {
            $total = 0;
            foreach (Week::WORKDAYS as $day) {
                for ($h = $hour; $h < $hour + self::SLOT_HOURS; $h++) {
                    $total += $counts[$day][$h] ?? 0;
                }
            }

            if ($total < $bestTotal || ($total === $bestTotal && $hour === 14)) {
                $best = $hour;
                $bestTotal = $total;
            }
        }

        $from = Carbon::createFromTime($best);
        $to = Carbon::createFromTime($best + self::SLOT_HOURS);

        return [
            'weekdays' => Week::WORKDAYS,
            'from' => $from->format('H:i'),
            'to' => $to->format('H:i'),
            'label' => 'weekdays '.$from->format('g').'–'.$to->format('g a'),
        ];
    }
}
