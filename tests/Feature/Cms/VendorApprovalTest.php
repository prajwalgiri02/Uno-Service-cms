<?php

namespace Tests\Feature\Cms;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\ShopStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use ActsAsAdmin, BuildsNajikData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->seedCategories();
        $this->admin = $this->admin(['name' => 'Asha Admin']);
    }

    public function test_tabs_count_each_status_and_pending_includes_changes_requested(): void
    {
        $this->shop(ShopStatus::PENDING, ['name' => 'Glow Beauty Parlour', 'area' => 'Putalisadak']);
        $this->shop(ShopStatus::CHANGES_REQUESTED, ['name' => 'Sharma Tailoring']);
        $this->shop(ShopStatus::APPROVED);
        $this->shop(ShopStatus::PAUSED);
        $this->shop(ShopStatus::REJECTED);

        $this->actingAs($this->admin)
            ->get('/cms/vendor-approvals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/vendors/index', false)
                ->where('filters.status', 'pending')
                ->where('counts', ['pending' => 2, 'approved' => 1, 'paused' => 1, 'rejected' => 1])
                ->has('shops.data', 2));

        $this->actingAs($this->admin)
            ->get('/cms/vendor-approvals?status=approved')
            ->assertInertia(fn (Assert $page) => $page->has('shops.data', 1)->where('shops.data.0.status', 'approved'));

        $this->actingAs($this->admin)
            ->get('/cms/vendor-approvals?search=glow')
            ->assertInertia(fn (Assert $page) => $page->has('shops.data', 1)->where('shops.data.0.name', 'Glow Beauty Parlour'));
    }

    public function test_the_review_page_shows_details_services_and_history(): void
    {
        $shop = $this->shop(ShopStatus::PENDING, ['landmark' => 'Opposite Putalisadak Chowk']);
        $shop->statusLogs()->create(['to_status' => 'pending', 'note' => 'Submitted from the app.']);

        $this->actingAs($this->admin)
            ->get("/cms/vendor-approvals/{$shop->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/vendors/show', false)
                ->where('shop.id', $shop->id)
                ->where('shop.category', "Beauty › Men's haircut")
                ->where('shop.landmark', 'Opposite Putalisadak Chowk')
                ->where('shop.checklist.check_photos', false)
                ->has('shop.services', 1)
                ->has('shop.history', 1)
                ->where('shop.history.0.by', 'System'));
    }

    public function test_the_checklist_can_be_ticked(): void
    {
        $shop = $this->shop(ShopStatus::PENDING);

        $this->actingAs($this->admin)
            ->patch("/cms/vendor-approvals/{$shop->id}/checklist", ['check_photos' => true, 'check_phone' => true])
            ->assertRedirect();

        $shop->refresh();
        $this->assertTrue($shop->check_photos);
        $this->assertTrue($shop->check_phone);
        $this->assertFalse($shop->check_pin);
    }

    public function test_approving_makes_the_shop_live_and_logs_the_admin(): void
    {
        $shop = $this->shop(ShopStatus::PENDING);

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'approve'])
            ->assertRedirect("/cms/vendor-approvals/{$shop->id}")
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame(ShopStatus::APPROVED, $shop->status);
        $this->assertSame($this->admin->id, $shop->reviewed_by);
        $this->assertNotNull($shop->reviewed_at);
        $this->assertDatabaseHas('shop_status_logs', ['shop_id' => $shop->id, 'user_id' => $this->admin->id, 'from_status' => 'pending', 'to_status' => 'approved']);
    }

    public function test_requesting_changes_and_rejecting_need_a_note(): void
    {
        $shop = $this->shop(ShopStatus::PENDING);

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'request_changes'])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'request_changes', 'note' => 'Add a shop front photo.'])
            ->assertSessionHas('success');

        $this->assertSame(ShopStatus::CHANGES_REQUESTED, $shop->refresh()->status);
        $this->assertSame('Add a shop front photo.', $shop->status_note);

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'reject'])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'reject', 'note' => 'Not a real shop.'])
            ->assertSessionHas('success');

        $this->assertSame(ShopStatus::REJECTED, $shop->refresh()->status);
        $this->assertSame(2, ShopStatusLog::query()->where('shop_id', $shop->id)->where('user_id', $this->admin->id)->count());
    }

    public function test_pausing_clears_open_today_and_restoring_clears_auto_pause(): void
    {
        $shop = $this->liveShop("Men's haircut", 200, ['no_wait_until' => now()->addMinutes(20)]);

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'pause'])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'pause', 'note' => 'Too many complaints.'])
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame(ShopStatus::PAUSED, $shop->status);
        $this->assertNull($shop->open_today_on);
        $this->assertNull($shop->no_wait_until);

        $shop->forceFill(['auto_paused_at' => now()])->save();

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$shop->id}/decision", ['action' => 'restore'])
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame(ShopStatus::APPROVED, $shop->status);
        $this->assertNull($shop->auto_paused_at);
    }

    public function test_actions_that_do_not_fit_the_status_flash_an_error(): void
    {
        $approved = $this->liveShop();
        $pending = $this->shop(ShopStatus::PENDING);

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$approved->id}/decision", ['action' => 'approve'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$pending->id}/decision", ['action' => 'restore'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$pending->id}/decision", ['action' => 'pause', 'note' => 'x'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post("/cms/vendor-approvals/{$pending->id}/decision", ['action' => 'delete'])
            ->assertSessionHasErrors('action');

        $this->assertSame(ShopStatus::APPROVED, $approved->refresh()->status);
        $this->assertSame(ShopStatus::PENDING, $pending->refresh()->status);
        $this->assertSame(0, ShopStatusLog::query()->count());
    }

    private function shop(ShopStatus $status, array $attributes = []): Shop
    {
        return $this->liveShop("Men's haircut", 200, ['status' => $status, 'open_today_on' => null, 'submitted_at' => now()->subDay(), ...$attributes]);
    }
}
