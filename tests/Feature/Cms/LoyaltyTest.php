<?php

namespace Tests\Feature\Cms;

use App\Models\PointTransaction;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use ActsAsAdmin, BuildsNajikData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();
        $this->admin = $this->admin();
    }

    public function test_stats_and_balances_come_from_the_ledger(): void
    {
        $sita = $this->customer(['name' => 'Sita Sharma']);
        $sujan = $this->customer(['name' => 'Sujan Karki']);
        $shop = $this->liveShop();

        $this->actingAsApi($sita)->postJson('/api/v1/check-ins', ['code' => $shop->check_in_code, ...$this->here()])->assertCreated();
        $this->actingAsApi($sujan)->postJson('/api/v1/check-ins', ['code' => $shop->check_in_code, ...$this->here()])->assertCreated();
        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$sujan->id}/adjust", ['points' => -5, 'note' => 'T-1 correction']);

        $this->actingAs($this->admin)
            ->get('/cms/loyalty-points')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/loyalty/index', false)
                ->where('stats.issued', 40)
                ->where('stats.issued_this_week', 40)
                ->where('stats.outstanding', 35)
                ->where('stats.unspent_percent', 88)
                ->where('stats.check_ins_this_month', 2)
                ->where('pointsPerVisit', 20)
                ->where('perkAt', 200)
                ->where('rewardsOpen', false)
                ->has('users.data', 3)
                ->where('users.data.0.name', 'Sita Sharma')
                ->where('users.data.0.visits', 1)
                ->where('users.data.0.points_earned', 20)
                ->where('users.data.1.balance', 15));

        $this->actingAs($this->admin)
            ->get('/cms/loyalty-points?search=sujan')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Sujan Karki'));
    }

    public function test_points_rules_change_the_next_check_in(): void
    {
        $this->actingAs($this->admin)
            ->put('/cms/loyalty-points/rules', ['points_per_visit' => 35, 'perk_at' => 300])
            ->assertSessionHas('success');

        $this->assertSame(35, app(SettingsService::class)->int('points_per_visit'));

        $shop = $this->liveShop();
        $this->actingAsApi($this->customer())
            ->postJson('/api/v1/check-ins', ['code' => $shop->check_in_code, ...$this->here()])
            ->assertCreated()
            ->assertJsonPath('data.points_earned', 35);

        $this->actingAs($this->admin)->put('/cms/loyalty-points/rules', ['points_per_visit' => 999, 'perk_at' => 0])->assertSessionHasErrors(['points_per_visit', 'perk_at']);
    }

    public function test_member_page_and_point_adjustments(): void
    {
        $user = $this->customer(['name' => 'Pooja Thapa', 'points_balance' => 30]);

        $this->actingAs($this->admin)
            ->post("/cms/loyalty-points/{$user->id}/adjust", ['points' => 20, 'note' => 'Missed check-in, T-316'])
            ->assertSessionHas('success');

        $this->assertSame(50, $user->refresh()->points_balance);
        $this->assertDatabaseHas('point_transactions', ['user_id' => $user->id, 'points' => 20, 'reason' => PointTransaction::REASON_ADJUSTMENT, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$user->id}/adjust", ['points' => -60, 'note' => 'too much'])->assertSessionHasErrors('points');
        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$user->id}/adjust", ['points' => 0, 'note' => 'nothing'])->assertSessionHasErrors('points');
        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$user->id}/adjust", ['points' => 5])->assertSessionHasErrors('note');
        $this->assertSame(50, $user->refresh()->points_balance);

        $this->actingAs($this->admin)
            ->get("/cms/loyalty-points/{$user->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/loyalty/show', false)
                ->where('member.name', 'Pooja Thapa')
                ->where('member.balance', 50)
                ->where('member.blocked', false)
                ->has('transactions', 1)
                ->where('transactions.0.reason', 'Adjustment')
                ->where('transactions.0.note', 'Missed check-in, T-316'));
    }

    public function test_blocking_locks_the_user_out_of_the_api_and_admins_cannot_be_blocked(): void
    {
        $user = $this->customer();

        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$user->id}/block")->assertSessionHas('success', 'User blocked. They are signed out of the app.');
        $this->assertTrue($user->refresh()->isBlocked());
        $this->actingAsApi($user)->getJson('/api/v1/me')->assertStatus(423);

        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$user->id}/block")->assertSessionHas('success', 'User unblocked.');
        $this->assertFalse($user->refresh()->isBlocked());
        $this->actingAsApi($user)->getJson('/api/v1/me')->assertOk();

        $other = $this->admin();
        $this->actingAs($this->admin)->post("/cms/loyalty-points/{$other->id}/block")->assertSessionHas('error');
        $this->assertFalse($other->refresh()->isBlocked());
    }
}
