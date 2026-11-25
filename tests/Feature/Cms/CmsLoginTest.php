<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsLoginTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    public function test_the_root_redirects_to_the_dashboard_and_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/cms/dashboard');
        $this->get('/cms/dashboard')->assertRedirect('/cms/login');
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/cms/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/sign-in', false));
    }

    public function test_an_admin_can_sign_in_and_see_the_dashboard(): void
    {
        $admin = $this->admin();

        $this->post('/cms/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/cms/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->refresh()->last_login_at);

        $this->get('/cms/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/dashboard/index', false)
                ->has('stats.active_vendors')
                ->has('chart.labels', 7)
                ->has('nudges.data'));
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $admin = $this->admin();

        $this->from('/cms/login')
            ->post('/cms/login', ['email' => $admin->email, 'password' => 'nope'])
            ->assertRedirect('/cms/login')
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_users_without_the_admin_role_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $user->assignRole('vendor');

        $this->post('/cms/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account cannot access the admin console.']);

        $this->assertGuest();
    }

    public function test_blocked_admins_cannot_sign_in(): void
    {
        $admin = $this->admin();
        $admin->update(['blocked_at' => now()]);

        $this->post('/cms/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_non_admins_with_a_session_cannot_open_the_cms(): void
    {
        $user = User::factory()->create();

        foreach (['/cms/dashboard', '/cms/vendor-approvals', '/cms/category-manager', '/cms/report-queue', '/cms/loyalty-points', '/cms/support-desk', '/cms/nudge-rules', '/cms/settings'] as $path) {
            $this->actingAs($user)->get($path)->assertForbidden();
        }
    }

    public function test_an_admin_can_sign_out(): void
    {
        $this->actingAs($this->admin())
            ->post('/cms/logout')
            ->assertRedirect('/cms/login');

        $this->assertGuest();
    }
}
