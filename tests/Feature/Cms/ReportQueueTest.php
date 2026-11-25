<?php

namespace Tests\Feature\Cms;

use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Models\Report;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class ReportQueueTest extends TestCase
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

    public function test_open_reports_list_with_counts_and_vendor_status(): void
    {
        $paused = $this->liveShop("Men's haircut", 200, ['name' => 'Classic Gents', 'status' => ShopStatus::PAUSED, 'auto_paused_at' => now()]);
        $active = $this->liveShop('Car wash', 300, ['name' => 'Quick Wash Point']);
        $this->report($paused, 'closed_when_open');
        $this->report($paused, 'price_higher');
        $this->report($active, 'price_higher');
        $this->report($active, 'other', ReportStatus::RESOLVED, now()->subHours(3));

        $this->actingAs($this->admin)
            ->get('/cms/report-queue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/reports/index', false)
                ->where('filters.status', 'open')
                ->has('reports.data', 3)
                ->where('stats.open', 3)
                ->where('stats.auto_paused', 1)
                ->where('stats.avg_resolution_hours', 3)
                ->has('resolutions', 4));

        $this->actingAs($this->admin)
            ->get('/cms/report-queue?search=classic')
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 2)
                ->where('reports.data.0.reports', 2)
                ->where('reports.data.0.vendor_status', 'auto_paused'));

        $this->actingAs($this->admin)
            ->get('/cms/report-queue?status=resolved')
            ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.vendor', 'Quick Wash Point'));
    }

    public function test_restoring_resolves_every_open_report_and_unpauses_the_vendor(): void
    {
        $shop = $this->liveShop("Men's haircut", 200, ['status' => ShopStatus::PAUSED, 'auto_paused_at' => now()]);
        $first = $this->report($shop, 'closed_when_open');
        $this->report($shop, 'price_higher');

        $this->actingAs($this->admin)
            ->post("/cms/report-queue/{$first->id}/resolve", ['resolution' => 'vendor_restored', 'note' => 'Called the owner.'])
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame(ShopStatus::APPROVED, $shop->status);
        $this->assertNull($shop->auto_paused_at);
        $this->assertSame(0, Report::query()->where('status', ReportStatus::OPEN)->count());
        $this->assertSame(2, Report::query()->where('resolved_by', $this->admin->id)->where('resolution', 'vendor_restored')->count());
    }

    public function test_keeping_the_vendor_paused_pauses_a_live_shop(): void
    {
        $shop = $this->liveShop();
        $report = $this->report($shop, 'rude_or_unsafe');

        $this->actingAs($this->admin)
            ->post("/cms/report-queue/{$report->id}/resolve", ['resolution' => 'vendor_paused'])
            ->assertSessionHas('success');

        $this->assertSame(ShopStatus::PAUSED, $shop->refresh()->status);
        $this->assertDatabaseHas('shop_status_logs', ['shop_id' => $shop->id, 'to_status' => 'paused', 'user_id' => $this->admin->id]);
    }

    public function test_dismissing_restores_an_auto_paused_vendor_and_warning_leaves_it_live(): void
    {
        $autoPaused = $this->liveShop("Men's haircut", 200, ['status' => ShopStatus::PAUSED, 'auto_paused_at' => now()]);
        $live = $this->liveShop('Car wash', 300);

        $this->actingAs($this->admin)->post('/cms/report-queue/'.$this->report($autoPaused, 'price_higher')->id.'/resolve', ['resolution' => 'dismissed']);
        $this->actingAs($this->admin)->post('/cms/report-queue/'.$this->report($live, 'price_higher')->id.'/resolve', ['resolution' => 'warned']);

        $this->assertSame(ShopStatus::APPROVED, $autoPaused->refresh()->status);
        $this->assertSame(ShopStatus::APPROVED, $live->refresh()->status);
    }

    public function test_resolved_reports_and_bad_input_are_rejected(): void
    {
        $shop = $this->liveShop();
        $report = $this->report($shop, 'price_higher', ReportStatus::RESOLVED, now());

        $this->actingAs($this->admin)->post("/cms/report-queue/{$report->id}/resolve", ['resolution' => 'warned'])->assertSessionHas('error');

        $open = $this->report($shop, 'price_higher');
        $this->actingAs($this->admin)->post("/cms/report-queue/{$open->id}/resolve", ['resolution' => 'banned'])->assertSessionHasErrors('resolution');
    }

    private function report(Shop $shop, string $reason, ReportStatus $status = ReportStatus::OPEN, $resolvedAt = null): Report
    {
        $report = $shop->reports()->create([
            'user_id' => $this->customer()->id,
            'reason' => $reason,
            'note' => $reason === 'other' ? 'something' : null,
            'status' => $status,
            'resolution' => $status === ReportStatus::RESOLVED ? 'warned' : null,
            'resolved_by' => $status === ReportStatus::RESOLVED ? $this->admin->id : null,
            'resolved_at' => $resolvedAt,
        ]);

        if ($resolvedAt) {
            $report->forceFill(['created_at' => $resolvedAt->copy()->subHours(3)])->save();
        }

        return $report;
    }
}
