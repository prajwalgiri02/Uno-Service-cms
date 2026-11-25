<?php

namespace Tests\Support;

use App\Enums\ShopStatus;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\TokenService;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait BuildsNajikData
{
    protected const LAT = 27.7041;

    protected const LNG = 85.3226;

    /**
     * Wednesday 25 Nov 2026, 5:30 pm Kathmandu: inside the default "after work" window.
     */
    protected function freezeAfterWork(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 17, 30, 0, 'Asia/Kathmandu'));
    }

    protected function seedCategories(): void
    {
        $this->seed(CategorySeeder::class);
    }

    protected function category(string $name): Category
    {
        return Category::query()->where('name', $name)->firstOrFail();
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->withPhone()->create($attributes);
        $user->assignRole('customer');
        app(ProfileService::class)->freeWindows($user);

        return $user;
    }

    /**
     * A live shop that confirmed "open today", offset north of the test point by $metersNorth.
     */
    protected function liveShop(string $category = "Men's haircut", int $metersNorth = 200, array $attributes = []): Shop
    {
        $owner = User::factory()->withPhone()->create();
        $owner->assignRole(['customer', 'vendor']);

        $shop = Shop::query()->create([
            'user_id' => $owner->id,
            'category_id' => $this->category($category)->id,
            'name' => fake()->company(),
            'latitude' => self::LAT + $metersNorth / 111320,
            'longitude' => self::LNG,
            'status' => ShopStatus::APPROVED,
            'check_in_code' => 'NJK-'.fake()->unique()->numberBetween(1000, 9999),
            'open_today_on' => today(),
            'open_confirmed_at' => now(),
            ...$attributes,
        ]);

        $shop->services()->create(['name' => $category, 'minutes' => 20, 'price_min' => 250, 'price_max' => 400]);

        return $shop;
    }

    protected function actingAsApi(User $user): static
    {
        $this->app->forgetScopedInstances();
        JWTAuth::clearResolvedInstances();
        Auth::forgetGuards();

        return $this->withToken(app(TokenService::class)->issueTokens($user)['access_token']);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    protected function here(): array
    {
        return ['latitude' => self::LAT, 'longitude' => self::LNG];
    }
}
