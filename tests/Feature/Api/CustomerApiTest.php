<?php

namespace Tests\Feature\Api;

use App\Enums\ShopStatus;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use BuildsNajikData, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();
        $this->user = $this->customer(['name' => 'Sita Sharma']);
    }

    public function test_config_and_category_tree_are_public(): void
    {
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.points.per_visit', 20);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Beauty')
            ->assertJsonPath('data.0.children.0.name', "Men's haircut")
            ->assertJsonPath('data.0.children.0.name_ne', 'पुरुष कपाल काट्ने');
    }

    public function test_profile_settings_pause_and_alert_health(): void
    {
        $this->actingAsApi($this->user)
            ->patchJson('/api/v1/me', ['name' => 'Sita', 'language' => 'ne', 'pings_per_day' => 3])
            ->assertOk()
            ->assertJsonPath('data.language', 'ne')
            ->assertJsonPath('data.pings_per_day', 3);

        $this->actingAsApi($this->user)->patchJson('/api/v1/me', ['pings_per_day' => 9])->assertJsonValidationErrors('pings_per_day');

        $this->actingAsApi($this->user)->postJson('/api/v1/me/pause-today')->assertOk()->assertJsonPath('data.paused_until', today()->addDay()->toIso8601String());
        $this->actingAsApi($this->user)->deleteJson('/api/v1/me/pause-today')->assertOk()->assertJsonPath('data.paused_until', null);

        $this->actingAsApi($this->user)->postJson('/api/v1/me/devices', ['device_id' => 'pixel-8', 'token' => 'fcm', 'platform' => 'android', 'location_permission' => 'always'])->assertOk();

        $this->actingAsApi($this->user)->getJson('/api/v1/me/alert-health')
            ->assertOk()
            ->assertJsonPath('data.healthy', false)
            ->assertJsonPath('data.checks.5.key', 'errands')
            ->assertJsonPath('data.checks.5.ok', false);
    }

    public function test_free_windows_start_from_presets_and_can_be_edited(): void
    {
        $windows = $this->actingAsApi($this->user)->getJson('/api/v1/me/free-windows')->assertOk()->assertJsonCount(4, 'data')->json('data');
        $lunch = collect($windows)->firstWhere('preset', 'lunch');

        $this->actingAsApi($this->user)->patchJson("/api/v1/me/free-windows/{$lunch['id']}", ['enabled' => true])->assertJsonPath('data.enabled', true);
        $this->actingAsApi($this->user)->deleteJson("/api/v1/me/free-windows/{$lunch['id']}")->assertStatus(422);

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/me/free-windows', ['title' => 'gym', 'weekdays' => [1, 3], 'from' => '07:00', 'to' => '06:00'])
            ->assertJsonValidationErrors('to');

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/me/blocked-places', ['label' => 'gym', 'latitude' => self::LAT, 'longitude' => self::LNG])
            ->assertCreated();

        $this->actingAsApi($this->user)->getJson('/api/v1/me/blocked-places')->assertJsonCount(3, 'data')->assertJsonPath('data.0.kind', 'home');
    }

    public function test_errands_can_be_added_listed_paused_and_completed(): void
    {
        $shop = $this->liveShop("Men's haircut", 200);
        $haircut = $this->category("Men's haircut");

        $id = $this->actingAsApi($this->user)
            ->postJson('/api/v1/errands', ['category_id' => $haircut->id, 'variant' => "men's cut", 'max_price' => 400, 'radius_m' => 500, 'only_no_wait' => false, 'duration' => '30_days'])
            ->assertCreated()
            ->assertJsonPath('data.title', "men's cut")
            ->json('data.id');

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/errands', ['category_id' => $haircut->id, 'variant' => "men's cut"])
            ->assertJsonValidationErrors('category_id');

        $this->actingAsApi($this->user)
            ->getJson('/api/v1/errands?'.http_build_query($this->here()))
            ->assertOk()
            ->assertJsonPath('data.errands.0.open_nearby', 1);

        $this->actingAsApi($this->user)
            ->getJson("/api/v1/errands/{$id}/matches?".http_build_query($this->here()))
            ->assertOk()
            ->assertJsonPath('data.shops.0.id', $shop->id)
            ->assertJsonPath('data.shops.0.distance_m', 200)
            ->assertJsonPath('data.shops.0.walk_min', 3);

        $this->actingAsApi($this->user)->patchJson("/api/v1/errands/{$id}", ['active' => false])->assertJsonPath('data.paused_note', 'paused by you');
        $this->actingAsApi($this->user)->postJson("/api/v1/errands/{$id}/done")->assertOk();
        $this->actingAsApi($this->user)->getJson('/api/v1/errands')->assertJsonCount(0, 'data.errands');

        $other = $this->customer();
        $this->actingAsApi($other)->patchJson("/api/v1/errands/{$id}", ['active' => true])->assertNotFound();
    }

    public function test_explore_lists_only_live_shops_closest_first_with_filters(): void
    {
        $far = $this->liveShop('Shoe repair', 900);
        $near = $this->liveShop("Men's haircut", 150, ['no_wait_until' => now()->addMinutes(20)]);
        $this->liveShop('Car wash', 300, ['status' => ShopStatus::PENDING]);

        $this->actingAsApi($this->user)
            ->getJson('/api/v1/shops?'.http_build_query($this->here()))
            ->assertOk()
            ->assertJsonCount(2, 'data.shops')
            ->assertJsonPath('data.shops.0.id', $near->id)
            ->assertJsonPath('data.shops.0.no_wait', true)
            ->assertJsonPath('data.shops.1.id', $far->id);

        $this->actingAsApi($this->user)
            ->getJson('/api/v1/shops?'.http_build_query([...$this->here(), 'no_wait' => 1]))
            ->assertJsonCount(1, 'data.shops');

        $this->actingAsApi($this->user)
            ->getJson('/api/v1/shops?'.http_build_query([...$this->here(), 'category_id' => $this->category('Repairs')->id]))
            ->assertJsonPath('data.shops.0.id', $far->id);

        $this->actingAsApi($this->user)
            ->getJson("/api/v1/shops/{$near->id}?".http_build_query($this->here()))
            ->assertOk()
            ->assertJsonPath('data.services.0.price_min', 250)
            ->assertJsonPath('data.distance_m', 150);

        $this->assertDatabaseHas('shop_views', ['shop_id' => $near->id, 'user_id' => $this->user->id]);

        $this->actingAsApi($this->user)->postJson("/api/v1/shops/{$near->id}/favorite")->assertOk();
        $this->actingAsApi($this->user)->getJson('/api/v1/shops/favorites')->assertJsonPath('data.0.id', $near->id);
    }

    public function test_home_summarises_the_trip_and_nearby_shops(): void
    {
        $this->user->errands()->create(['category_id' => $this->category("Men's haircut")->id, 'title' => "men's haircut", 'radius_m' => 500]);
        $this->user->errands()->create(['category_id' => $this->category('Shoe repair')->id, 'title' => 'shoe repair', 'radius_m' => 500]);
        $this->liveShop("Men's haircut", 200);
        $this->liveShop('Shoe repair', 380);

        $this->actingAsApi($this->user)
            ->getJson('/api/v1/home?'.http_build_query($this->here()))
            ->assertOk()
            ->assertJsonPath('data.name', 'Sita Sharma')
            ->assertJsonPath('data.trip.errand_count', 2)
            ->assertJsonPath('data.trip.within_m', 380)
            ->assertJsonCount(2, 'data.near_you');
    }

    public function test_check_in_with_the_shop_code_awards_points_and_completes_errands(): void
    {
        $shop = $this->liveShop("Men's haircut", 100);
        $errand = $this->user->errands()->create(['category_id' => $this->category('Beauty')->id, 'title' => 'beauty', 'radius_m' => 500]);
        $digits = substr($shop->check_in_code, 4);

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/check-ins', ['code' => '0000', ...$this->here()])
            ->assertJsonValidationErrors('code');

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/check-ins', ['code' => $digits, 'latitude' => self::LAT + 0.02, 'longitude' => self::LNG])
            ->assertJsonPath('errors.code.0', 'You need to be at the shop to check in.');

        $visitId = $this->actingAsApi($this->user)
            ->postJson('/api/v1/check-ins', ['code' => strtolower($shop->check_in_code), ...$this->here()])
            ->assertCreated()
            ->assertJsonPath('data.points_earned', 20)
            ->assertJsonPath('data.points', 20)
            ->assertJsonPath('data.streak_weeks', 1)
            ->assertJsonPath('data.visit.first_visit', true)
            ->json('data.visit.id');

        $this->assertNotNull($errand->refresh()->done_at);

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/check-ins', ['code' => $digits, ...$this->here()])
            ->assertJsonPath('errors.code.0', 'You already checked in here today.');

        $this->actingAsApi($this->user)->postJson("/api/v1/check-ins/{$visitId}/review", ['stars' => 5, 'text' => 'quick and clean'])->assertCreated();
        $this->actingAsApi($this->user)->postJson("/api/v1/check-ins/{$visitId}/review", ['stars' => 4])->assertJsonValidationErrors('stars');

        $this->actingAsApi($this->user)->getJson('/api/v1/me/stats')
            ->assertJsonPath('data.points', 20)
            ->assertJsonPath('data.points_to_next_perk', 180)
            ->assertJsonPath('data.errands_done_this_month', 1);

        $this->actingAsApi($this->user)->getJson('/api/v1/me/visits?month=2026-11')
            ->assertJsonCount(1, 'data.visits')
            ->assertJsonPath('data.visits.0.review.stars', 5);
    }

    public function test_the_shop_can_confirm_a_visit_with_the_customer_code(): void
    {
        $shop = $this->liveShop();
        $code = $this->actingAsApi($this->user)->getJson('/api/v1/check-ins/visit-code')->assertOk()->json('data.code');

        $this->actingAsApi($shop->owner)->postJson('/api/v1/vendor/check-ins', ['code' => $code])
            ->assertCreated()
            ->assertJsonPath('data.customer', 'Sita Sharma')
            ->assertJsonPath('data.visits_today', 1);

        $this->assertSame(20, $this->user->refresh()->points_balance);
        $this->assertSame('customer_code', CheckIn::query()->value('method')->value);

        $this->actingAsApi($shop->owner)->postJson('/api/v1/vendor/check-ins', ['code' => $code])->assertJsonValidationErrors('code');
    }

    public function test_two_open_reports_auto_pause_a_shop(): void
    {
        $shop = $this->liveShop();

        $this->actingAsApi($this->user)->postJson("/api/v1/shops/{$shop->id}/report", ['reason' => 'closed_when_open'])->assertCreated();
        $this->actingAsApi($this->user)->postJson("/api/v1/shops/{$shop->id}/report", ['reason' => 'price_higher'])->assertJsonValidationErrors('reason');
        $this->actingAsApi($this->user)->postJson("/api/v1/shops/{$shop->id}/report", ['reason' => 'other'])->assertJsonValidationErrors('note');

        $this->assertSame(ShopStatus::APPROVED, $shop->refresh()->status);

        $this->actingAsApi($this->customer())->postJson("/api/v1/shops/{$shop->id}/report", ['reason' => 'waited_long'])->assertCreated();

        $this->assertSame(ShopStatus::PAUSED, $shop->refresh()->status);
        $this->assertNotNull($shop->auto_paused_at);
        $this->actingAsApi($this->user)->getJson("/api/v1/shops/{$shop->id}")->assertNotFound();
    }

    public function test_support_tickets_can_be_opened_and_answered(): void
    {
        $id = $this->actingAsApi($this->user)
            ->postJson('/api/v1/support-tickets', ['subject' => 'Points missing', 'message' => 'I checked in yesterday.'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'user')
            ->json('data.id');

        $this->actingAsApi($this->user)->postJson("/api/v1/support-tickets/{$id}/messages", ['body' => 'Any update?'])->assertJsonCount(2, 'data.messages');
        $this->actingAsApi($this->customer())->getJson("/api/v1/support-tickets/{$id}")->assertNotFound();
    }
}
