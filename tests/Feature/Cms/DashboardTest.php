<?php

namespace Tests\Feature\Cms;

use App\Enums\NudgeResult;
use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Enums\TicketType;
use App\Models\Nudge;
use App\Models\Shop;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use ActsAsAdmin, BuildsNajikData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();
    }

    public function test_stats_chart_and_attention_reflect_the_period(): void
    {
        $customer = $this->customer();
        $haircut = $this->liveShop("Men's haircut", 200);
        $shoe = $this->liveShop('Shoe repair', 300, ['open_today_on' => null]);
        $this->liveShop('Café', 300, ['status' => ShopStatus::PENDING]);
        $customer->errands()->create(['category_id' => $this->category("Men's haircut")->id, 'title' => 'haircut']);

        $this->nudge($customer, $haircut, NudgeResult::VISITED, now()->subDay());
        $this->nudge($customer, $shoe, NudgeResult::SENT, now());
        $this->nudge($customer, $haircut, NudgeResult::OPENED, now()->subDays(20));
        $haircut->reports()->create(['user_id' => $customer->id, 'reason' => 'price_higher', 'status' => ReportStatus::OPEN]);

        $this->actingAs($this->admin())
            ->get('/cms/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/dashboard/index', false)
                ->where('period', 7)
                ->where('stats.active_vendors', 2)
                ->where('stats.active_wishlists', 1)
                ->where('stats.nudges_sent', 2)
                ->where('stats.visit_rate', 50)
                ->where('stats.open_reports', 1)
                ->has('chart.labels', 7)
                ->where('chart.nudges.6', 1)
                ->where('attention.pending_vendors', 1)
                ->where('attention.not_confirmed_today', 1)
                ->where('attention.alert_delivery_rate', 100)
                ->has('nudges.data', 3));

        $this->actingAs($this->admin())
            ->get('/cms/dashboard?period=30')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period', 30)
                ->where('stats.nudges_sent', 3)
                ->has('chart.labels', 30));
    }

    public function test_recent_nudges_filter_by_result_and_category(): void
    {
        $customer = $this->customer();
        $haircut = $this->liveShop("Men's haircut", 200);
        $shoe = $this->liveShop('Shoe repair', 300);
        $this->nudge($customer, $haircut, NudgeResult::VISITED, now());
        $this->nudge($customer, $shoe, NudgeResult::IGNORED, now());

        $this->actingAs($this->admin())
            ->get('/cms/dashboard?result=visited')
            ->assertInertia(fn (Assert $page) => $page->has('nudges.data', 1)->where('nudges.data.0.vendor', $haircut->name));

        $this->actingAs($this->admin())
            ->get('/cms/dashboard?category_id='.$this->category('Shoe repair')->id)
            ->assertInertia(fn (Assert $page) => $page->has('nudges.data', 1)->where('nudges.data.0.result', 'ignored'));

        $this->actingAs($this->admin())->get('/cms/dashboard?period=9')->assertSessionHasErrors('period');
    }

    public function test_export_streams_a_csv_of_nudges_in_the_period(): void
    {
        $customer = $this->customer(['name' => 'Sita Sharma']);
        $shop = $this->liveShop();
        $recent = $this->nudge($customer, $shop, NudgeResult::VISITED, now());
        $this->nudge($customer, $shop, NudgeResult::SENT, now()->subDays(20));

        $response = $this->actingAs($this->admin())->get('/cms/dashboard/export?period=7');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Nudge ID', $lines[0]);
        $this->assertStringContainsString("N-{$recent->id}", $lines[1]);
        $this->assertStringContainsString('Sita Sharma', $lines[1]);
        $this->assertStringContainsString($shop->name, $lines[1]);
    }

    public function test_non_admins_cannot_open_the_dashboard_or_export(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get('/cms/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/cms/dashboard/export')->assertForbidden();
    }

    public function test_admins_get_shared_badges(): void
    {
        $customer = $this->customer();
        $shop = $this->liveShop();
        $this->liveShop('Café', 300, ['status' => ShopStatus::PENDING]);
        $shop->reports()->create(['user_id' => $customer->id, 'reason' => 'price_higher', 'status' => ReportStatus::OPEN]);
        app(SupportService::class)->open($customer, TicketType::USER, 'Help', 'Body');

        $this->actingAs($this->admin())
            ->get('/cms/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('badges.vendor-approvals', 1)
                ->where('badges.report-queue', 1)
                ->where('badges.support-desk', 1));
    }

    private function nudge(User $user, Shop $shop, NudgeResult $result, $at): Nudge
    {
        $nudge = Nudge::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'shop_ids' => [$shop->id],
            'errand_ids' => [],
            'distance_m' => 250,
            'title' => 'haircut · 250 m away',
            'body' => 'open now',
            'result' => $result,
            'push_delivered' => true,
        ]);
        $nudge->shops()->attach($shop->id);
        $nudge->forceFill(['created_at' => $at])->save();

        return $nudge;
    }
}
