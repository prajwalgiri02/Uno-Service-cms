<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class NudgeEngineTest extends TestCase
{
    use BuildsNajikData, RefreshDatabase;

    private User $user;

    /** @var list<array{token: string, title: string}> */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();

        $this->app->instance(PushGateway::class, new class($this->pushes) implements PushGateway
        {
            public function __construct(private array &$log) {}

            public function send(string $deviceToken, string $title, string $body, array $data = []): bool
            {
                $this->log[] = ['token' => $deviceToken, 'title' => $title];

                return true;
            }
        });

        $this->user = $this->customer();
        $this->user->deviceTokens()->create(['device_id' => 'pixel', 'token' => 'fcm-token', 'notifications_allowed' => true]);
        $this->user->errands()->create(['category_id' => $this->category("Men's haircut")->id, 'title' => "men's haircut", 'max_price' => 400, 'radius_m' => 500]);
    }

    public function test_it_nudges_when_a_matching_shop_is_open_and_close(): void
    {
        $shop = $this->liveShop("Men's haircut", 250);

        $this->check()
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.reason', 'sent')
            ->assertJsonPath('data.nudge.shops.0.id', $shop->id)
            ->assertJsonPath('data.nudge.distance_m', 250);

        $this->assertCount(1, $this->pushes);
        $this->assertDatabaseHas('nudges', ['user_id' => $this->user->id, 'shop_id' => $shop->id, 'push_delivered' => true]);
    }

    public function test_it_stays_silent_outside_free_windows(): void
    {
        $this->liveShop();
        $this->travelTo(now()->setTime(11, 0));

        $this->check()->assertJsonPath('data.sent', false)->assertJsonPath('data.reason', 'outside_free_window');
    }

    public function test_free_windows_rule_can_be_switched_off(): void
    {
        $this->liveShop();
        app(SettingsService::class)->update(['free_windows_only' => false]);
        $this->travelTo(now()->setTime(11, 0));

        $this->check()->assertJsonPath('data.sent', true);
    }

    public function test_it_ignores_shops_beyond_walking_distance_or_not_confirmed_open(): void
    {
        $this->liveShop("Men's haircut", 650);
        $this->liveShop("Men's haircut", 200, ['open_today_on' => null]);

        $this->check()->assertJsonPath('data.reason', 'no_match');
    }

    public function test_it_respects_price_and_no_wait_filters(): void
    {
        $this->user->errands()->update(['max_price' => 100]);
        $this->liveShop();

        $this->check()->assertJsonPath('data.reason', 'no_match');

        $this->user->errands()->update(['max_price' => 400, 'only_no_wait' => true]);
        $this->check()->assertJsonPath('data.reason', 'no_match');

        $this->liveShop("Men's haircut", 300, ['no_wait_until' => now()->addMinutes(30)]);
        $this->check()->assertJsonPath('data.sent', true);
    }

    public function test_the_daily_cap_uses_the_lower_of_user_and_platform_limits(): void
    {
        $this->user->update(['pings_per_day' => 1]);
        $this->liveShop("Men's haircut", 200);
        $this->liveShop("Men's haircut", 300);

        $this->check()->assertJsonPath('data.sent', true);
        $this->check()->assertJsonPath('data.reason', 'daily_cap');
    }

    public function test_the_same_vendor_is_not_nudged_again_within_the_gap(): void
    {
        $shop = $this->liveShop();

        $this->check()->assertJsonPath('data.sent', true);

        $this->travel(2)->days();
        $shop->update(['open_today_on' => today()]);
        $this->check()->assertJsonPath('data.reason', 'no_match');

        $this->travel(7)->days();
        $shop->update(['open_today_on' => today()]);
        $this->check()->assertJsonPath('data.sent', true);
    }

    public function test_two_errands_nearby_become_one_combined_nudge(): void
    {
        $this->user->errands()->create(['category_id' => $this->category('Shoe repair')->id, 'title' => 'shoe repair', 'radius_m' => 500]);
        $this->liveShop("Men's haircut", 200);
        $this->liveShop('Shoe repair', 380);

        $this->check()
            ->assertJsonPath('data.nudge.combined', true)
            ->assertJsonPath('data.nudge.title', 'got 20 min? 2 errands, 1 trip')
            ->assertJsonCount(2, 'data.nudge.shops');
    }

    public function test_main_category_errands_match_subcategory_shops(): void
    {
        $this->user->errands()->delete();
        $this->user->errands()->create(['category_id' => $this->category('Beauty')->id, 'title' => 'beauty', 'radius_m' => 500]);
        $this->liveShop('Facial', 200);

        $this->check()->assertJsonPath('data.sent', true);
    }

    public function test_it_is_silent_when_paused_or_at_a_blocked_place(): void
    {
        $this->liveShop();

        $this->user->blockedPlaces()->create(['kind' => 'home', 'label' => 'home', 'latitude' => self::LAT, 'longitude' => self::LNG, 'radius_m' => 150]);
        $this->check()->assertJsonPath('data.reason', 'at_blocked_place');

        $this->user->blockedPlaces()->update(['enabled' => false]);
        $this->actingAsApi($this->user)->postJson('/api/v1/me/pause-today')->assertOk();
        $this->check()->assertJsonPath('data.reason', 'paused');
    }

    public function test_opening_a_nudge_and_checking_in_marks_it_visited(): void
    {
        $shop = $this->liveShop();
        $nudgeId = $this->check()->json('data.nudge.id');

        $this->actingAsApi($this->user)->postJson("/api/v1/nudges/{$nudgeId}/opened")->assertJsonPath('data.result', 'opened');

        $this->actingAsApi($this->user)
            ->postJson('/api/v1/check-ins', ['code' => $shop->check_in_code, ...$this->here()])
            ->assertCreated();

        $this->assertDatabaseHas('nudges', ['id' => $nudgeId, 'result' => 'visited']);
        $this->assertDatabaseHas('check_ins', ['nudge_id' => $nudgeId]);
    }

    public function test_users_who_never_open_nudges_fall_back_to_a_weekly_summary(): void
    {
        foreach ([200, 300, 400] as $meters) {
            $this->liveShop("Men's haircut", $meters);
            $this->check()->assertJsonPath('data.sent', true);
            $this->travel(2)->days();
        }

        $this->liveShop("Men's haircut", 250);
        $this->check()->assertJsonPath('data.reason', 'weekly_summary');
    }

    private function check()
    {
        return $this->actingAsApi($this->user)->postJson('/api/v1/nudges/check', $this->here());
    }
}
