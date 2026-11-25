<?php

namespace Tests\Feature\Api;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class VendorApiTest extends TestCase
{
    use BuildsNajikData, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();
        $this->owner = $this->customer(['name' => 'Ram']);
    }

    public function test_a_customer_can_submit_a_shop_for_approval(): void
    {
        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/shop')->assertNotFound()->assertJsonPath('message', 'Set up your shop first.');

        $this->actingAsApi($this->owner)
            ->postJson('/api/v1/vendor/shop', [
                'name' => 'Style Point Salon',
                'category_id' => $this->category("Men's haircut")->id,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'area' => 'Putalisadak',
                'opening_hours' => ['mon' => ['open' => '09:00', 'close' => '19:00'], 'sat' => null],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_live', false)
            ->assertJsonPath('data.phone', $this->owner->phone);

        $this->assertTrue($this->owner->refresh()->isVendor());
        $this->assertMatchesRegularExpression('/^NJK-\d{4}$/', Shop::query()->value('check_in_code'));

        $this->actingAsApi($this->owner)
            ->postJson('/api/v1/vendor/shop', ['name' => 'Second', 'category_id' => $this->category('Café')->id, 'latitude' => 1, 'longitude' => 1])
            ->assertJsonValidationErrors('name');
    }

    public function test_opening_hours_are_validated(): void
    {
        $this->actingAsApi($this->owner)
            ->postJson('/api/v1/vendor/shop', [
                'name' => 'Late Cafe', 'category_id' => $this->category('Café')->id, 'latitude' => self::LAT, 'longitude' => self::LNG,
                'opening_hours' => ['mon' => ['open' => '20:00', 'close' => '09:00']],
            ])
            ->assertJsonValidationErrors('opening_hours');
    }

    public function test_services_can_be_managed_with_price_checks_and_photos(): void
    {
        Storage::fake('public');
        $shop = $this->pendingShop();

        $this->actingAsApi($this->owner)
            ->postJson('/api/v1/vendor/services', ['name' => 'Haircut', 'minutes' => 25, 'price_min' => 400, 'price_max' => 250])
            ->assertJsonPath('errors.price_max.0', 'The first number should be the lower one.');

        $id = $this->actingAsApi($this->owner)
            ->post('/api/v1/vendor/services', ['name' => 'Haircut', 'minutes' => 25, 'price_min' => 250, 'price_max' => 400, 'photo' => UploadedFile::fake()->image('cut.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        Storage::disk('public')->assertExists($shop->services()->first()->photo_path);

        $this->actingAsApi($this->owner)->patchJson("/api/v1/vendor/services/{$id}", ['price_max' => 450])->assertJsonPath('data.price_max', 450);
        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/services')->assertJsonCount(1, 'data');

        $this->actingAsApi($this->owner)->post('/api/v1/vendor/shop/photos', ['photo' => UploadedFile::fake()->image('front.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/shop')->assertJsonCount(1, 'data.photos');

        $this->actingAsApi($this->owner)->deleteJson("/api/v1/vendor/services/{$id}")->assertOk();
    }

    public function test_open_today_needs_approval_and_no_wait_expires_by_rule(): void
    {
        $shop = $this->pendingShop();

        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/open-today', ['open' => true])->assertJsonValidationErrors('open');

        $shop->update(['status' => ShopStatus::APPROVED]);
        app(SettingsService::class)->update(['no_wait_minutes' => 45]);

        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/no-wait', ['on' => true])->assertJsonValidationErrors('on');
        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/open-today', ['open' => true])->assertOk()->assertJsonPath('data.open_today', true);
        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/no-wait', ['on' => true])
            ->assertJsonPath('data.no_wait', true)
            ->assertJsonPath('data.no_wait_until', now()->addMinutes(45)->toIso8601String());

        $this->travel(46)->minutes();
        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/shop')->assertJsonPath('data.no_wait', false);

        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/open-today', ['open' => false])->assertJsonPath('data.open_today', false);
    }

    public function test_offers_busy_map_code_rotation_reviews_and_insights(): void
    {
        $shop = $this->pendingShop();
        $shop->update(['status' => ShopStatus::APPROVED]);

        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/offers')
            ->assertOk()
            ->assertJsonCount(7, 'data.busy.levels')
            ->assertJsonPath('data.busy.suggestion.label', 'weekdays 2–4 pm')
            ->assertJsonPath('data.current_offer', null);

        $this->actingAsApi($this->owner)
            ->postJson('/api/v1/vendor/offers', ['percent' => 10, 'weekdays' => [1, 2, 3, 4, 5, 7], 'from' => '14:00', 'to' => '16:00'])
            ->assertCreated()
            ->assertJsonPath('data.label', '10% off · weekdays 2–4 pm');

        $this->actingAsApi($this->owner)->deleteJson('/api/v1/vendor/offers/current')->assertOk();
        $this->assertNull($shop->refresh()->currentOffer);

        $old = $shop->check_in_code;
        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/check-in-code/rotate')->assertOk();
        $this->assertNotSame($old, $shop->refresh()->check_in_code);

        $customer = $this->customer(['name' => 'Sujan Karki']);
        $shop->update(['open_today_on' => today()]);
        $visit = $this->actingAsApi($customer)->postJson('/api/v1/check-ins', ['code' => $shop->check_in_code, ...$this->here()])->assertCreated()->json('data.visit.id');
        $this->actingAsApi($customer)->postJson("/api/v1/check-ins/{$visit}/review", ['stars' => 2, 'text' => 'waited 20 minutes'])->assertCreated();

        $reviewId = $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/reviews?filter=needs_reply')
            ->assertJsonPath('data.reviews.0.name', 'Sujan K.')
            ->assertJsonPath('data.summary.needs_reply', 1)
            ->json('data.reviews.0.id');

        $this->actingAsApi($this->owner)->postJson("/api/v1/vendor/reviews/{$reviewId}/reply", ['reply' => 'Sorry, we added a chair.'])->assertOk();
        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/reviews?filter=needs_reply')->assertJsonCount(0, 'data.reviews');

        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/today')
            ->assertJsonPath('data.visits_today', 1)
            ->assertJsonCount(7, 'data.last_7_days');

        $this->actingAsApi($this->owner)->getJson('/api/v1/vendor/insights?period=week')
            ->assertJsonPath('data.checked_in', 1)
            ->assertJsonPath('data.new_customers', 1)
            ->assertJsonPath('data.best_time', '5–6 pm');
    }

    public function test_editing_a_shop_sent_back_for_changes_resubmits_it(): void
    {
        $shop = $this->pendingShop();
        $shop->update(['status' => ShopStatus::CHANGES_REQUESTED]);

        $this->actingAsApi($this->owner)->patchJson('/api/v1/vendor/shop', ['landmark' => 'Opposite Putalisadak Chowk'])->assertJsonPath('data.status', 'pending');
    }

    private function pendingShop(): Shop
    {
        $this->actingAsApi($this->owner)->postJson('/api/v1/vendor/shop', [
            'name' => 'Style Point Salon',
            'category_id' => $this->category("Men's haircut")->id,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertCreated();

        return Shop::query()->firstOrFail();
    }
}
