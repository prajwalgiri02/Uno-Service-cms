<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    private User $admin;

    private array $rules = [
        'daily_nudge_cap' => 3,
        'same_vendor_gap_days' => 5,
        'walking_min_m' => 250,
        'walking_max_m' => 600,
        'trigger_min_m' => 150,
        'no_wait_minutes' => 60,
        'auto_pause_reports' => 3,
        'combined_nudge' => false,
        'weekly_summary_fallback' => true,
        'vendor_confirmation_required' => true,
        'free_windows_only' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->admin(['name' => 'Asha Admin', 'email' => 'asha@najik.app']);
    }

    public function test_nudge_rules_show_defaults_and_save(): void
    {
        $this->actingAs($this->admin)
            ->get('/cms/nudge-rules')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/nudge-rules/index', false)
                ->where('rules.daily_nudge_cap', 2)
                ->where('rules.walking_max_m', 500)
                ->missing('rules.points_per_visit'));

        $this->actingAs($this->admin)->put('/cms/nudge-rules', $this->rules)->assertSessionHas('success');

        $settings = app(SettingsService::class);
        $this->assertSame(3, $settings->int('daily_nudge_cap'));
        $this->assertSame(600, $settings->int('walking_max_m'));
        $this->assertFalse($settings->bool('combined_nudge'));
        $this->assertFalse($settings->bool('free_windows_only'));
        $this->assertSame(20, $settings->int('points_per_visit'));
    }

    public function test_nudge_rules_reject_inconsistent_distances(): void
    {
        $this->actingAs($this->admin)
            ->put('/cms/nudge-rules', [...$this->rules, 'walking_min_m' => 500, 'walking_max_m' => 300])
            ->assertSessionHasErrors(['walking_max_m' => 'The far end of the walking distance must be at least the near end.']);

        $this->actingAs($this->admin)
            ->put('/cms/nudge-rules', [...$this->rules, 'trigger_min_m' => 400])
            ->assertSessionHasErrors(['trigger_min_m' => 'The trigger distance cannot be larger than the walking distance.']);

        $this->actingAs($this->admin)
            ->put('/cms/nudge-rules', [...$this->rules, 'daily_nudge_cap' => 9])
            ->assertSessionHasErrors('daily_nudge_cap');

        $this->assertSame(2, app(SettingsService::class)->int('daily_nudge_cap'));
    }

    public function test_settings_page_profile_and_password(): void
    {
        $this->actingAs($this->admin)
            ->get('/cms/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/settings/index', false)
                ->where('profile.email', 'asha@najik.app')
                ->has('admins', 1));

        $this->actingAs($this->admin)
            ->put('/cms/settings/profile', ['name' => 'Asha K.', 'email' => 'asha.k@najik.app'])
            ->assertSessionHas('success');
        $this->assertSame('asha.k@najik.app', $this->admin->refresh()->email);

        $other = $this->admin(['email' => 'taken@najik.app']);
        $this->actingAs($this->admin)->put('/cms/settings/profile', ['name' => 'Asha', 'email' => $other->email])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)
            ->put('/cms/settings/password', ['current_password' => 'wrong', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($this->admin)
            ->put('/cms/settings/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->admin)
            ->put('/cms/settings/password', ['current_password' => 'password', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('newpassword123', $this->admin->refresh()->password));
    }

    public function test_a_new_admin_can_be_added_and_sign_in(): void
    {
        $this->actingAs($this->admin)
            ->post('/cms/settings/admins', ['name' => 'Bikash', 'email' => 'bikash@najik.app', 'password' => 'strongpass123'])
            ->assertSessionHas('success');

        $new = User::query()->where('email', 'bikash@najik.app')->firstOrFail();
        $this->assertTrue($new->isAdmin());
        $this->assertTrue(Hash::check('strongpass123', $new->password));

        $this->actingAs($this->admin)->post('/cms/settings/admins', ['name' => 'Dup', 'email' => 'bikash@najik.app', 'password' => 'strongpass123'])->assertSessionHasErrors('email');

        auth()->logout();
        $this->post('/cms/login', ['email' => 'bikash@najik.app', 'password' => 'strongpass123'])->assertRedirect('/cms/dashboard');
    }
}
