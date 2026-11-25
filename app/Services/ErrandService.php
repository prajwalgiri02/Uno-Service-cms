<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Errand;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ErrandService
{
    public const DURATIONS = ['till_done' => null, '7_days' => 7, '30_days' => 30];

    private const ON_THE_WAY_M = 500;

    public function __construct(
        private readonly ShopFinder $finder,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(User $user, array $data): Errand
    {
        $category = Category::query()->active()->findOrFail($data['category_id']);

        $exists = $user->errands()->open()->where('category_id', $category->id)
            ->where('variant', $data['variant'] ?? null)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['category_id' => 'That is already on your list.']);
        }

        return $user->errands()->create([
            'category_id' => $category->id,
            'title' => $data['title'] ?? $data['variant'] ?? $category->name,
            'variant' => $data['variant'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'radius_m' => $data['radius_m'] ?? 1000,
            'only_no_wait' => $data['only_no_wait'] ?? false,
            'offers_only' => $data['offers_only'] ?? false,
            'women_run' => $data['women_run'] ?? false,
            'expires_at' => $this->expiresAt($data['duration'] ?? 'till_done'),
            'kept_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Errand $errand, array $data): Errand
    {
        if (array_key_exists('duration', $data)) {
            $data['expires_at'] = $this->expiresAt($data['duration']);
            unset($data['duration']);
        }

        if (array_key_exists('active', $data)) {
            $data['paused_note'] = $data['active'] ? null : 'paused by you';
        }

        $errand->fill($data)->save();

        return $errand;
    }

    public function keep(Errand $errand): Errand
    {
        $errand->forceFill(['kept_at' => now()])->save();

        return $errand;
    }

    public function markDone(Errand $errand): Errand
    {
        $errand->forceFill(['done_at' => now(), 'active' => false])->save();

        return $errand;
    }

    /**
     * Pauses every errand of a category ("not feeling it?").
     */
    public function pauseCategory(User $user, Category $category): int
    {
        return $user->errands()->open()->whereIn('category_id', $category->matchingIds())
            ->update(['active' => false, 'paused_note' => 'paused · not this category']);
    }

    /**
     * @return Collection<int, Shop>
     */
    public function matches(Errand $errand, float $lat, float $lng): Collection
    {
        return $this->finder->near($lat, $lng, $errand->radius_m, $errand->category->matchingIds(), [
            'no_wait' => $errand->only_no_wait,
            'offers' => $errand->offers_only,
            'women_run' => $errand->women_run,
        ])->filter(fn (Shop $shop) => $errand->max_price === null || ($shop->priceMin() ?? 0) <= $errand->max_price)->values();
    }

    /**
     * Shops for the user's other watched errands that are close too.
     *
     * @return Collection<int, Shop>
     */
    public function alsoOnTheWay(User $user, Errand $except, float $lat, float $lng): Collection
    {
        $categoryIds = $user->errands()->watching()->whereKeyNot($except->id)->with('category.children')->get()
            ->flatMap(fn (Errand $e) => $e->category->matchingIds())
            ->unique()->values()->all();

        if ($categoryIds === []) {
            return new Collection;
        }

        return $this->finder->near($lat, $lng, self::ON_THE_WAY_M, $categoryIds, limit: 3);
    }

    private function expiresAt(?string $duration): ?Carbon
    {
        $days = self::DURATIONS[$duration ?? 'till_done'] ?? null;

        return $days ? now()->addDays($days) : null;
    }
}
