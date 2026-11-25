<?php

namespace Tests\Feature\Cms;

use App\Contracts\PushGateway;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class SupportDeskTest extends TestCase
{
    use ActsAsAdmin, BuildsNajikData, RefreshDatabase;

    private User $admin;

    /** @var list<array{token: string, title: string, body: string}> */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAfterWork();
        $this->admin = $this->admin(['name' => 'Asha Admin']);

        $this->app->instance(PushGateway::class, new class($this->pushes) implements PushGateway
        {
            public function __construct(private array &$log) {}

            public function send(string $deviceToken, string $title, string $body, array $data = []): bool
            {
                $this->log[] = ['token' => $deviceToken, 'title' => $title, 'body' => $body];

                return true;
            }
        });
    }

    public function test_tickets_list_with_type_filter_search_and_stats(): void
    {
        $user = $this->customer(['name' => 'Sita Sharma']);
        $vendor = $this->customer(['name' => 'Style Point']);
        $this->ticket($user, TicketType::USER, 'Nudge arrived at a closed shop');
        $this->ticket($vendor, TicketType::VENDOR, 'Cannot change opening hours');
        $resolved = $this->ticket($user, TicketType::USER, 'Old question');
        $resolved->forceFill(['status' => TicketStatus::RESOLVED, 'resolved_at' => now()])->save();
        $late = $this->ticket($user, TicketType::USER, 'Alerts not firing');
        $late->forceFill(['due_at' => now()->addHours(2)])->save();

        $this->actingAs($this->admin)
            ->get('/cms/support-desk')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/support/index', false)
                ->has('tickets.data', 3)
                ->where('tickets.data.0.subject', 'Alerts not firing')
                ->where('stats.open', 3)
                ->where('stats.new_today', 4)
                ->where('stats.due_soon', 1)
                ->where('stats.avg_first_reply_hours', null));

        $this->actingAs($this->admin)
            ->get('/cms/support-desk?type=vendor')
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.from', 'Style Point'));

        $this->actingAs($this->admin)
            ->get('/cms/support-desk?search=closed')
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.number', 'T-1'));

        $this->actingAs($this->admin)
            ->get('/cms/support-desk?status=resolved')
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.subject', 'Old question'));
    }

    public function test_a_ticket_thread_can_be_read_and_answered(): void
    {
        $user = $this->customer(['name' => 'Sujan Karki']);
        $user->deviceTokens()->create(['device_id' => 'pixel', 'token' => 'fcm-token']);
        $ticket = $this->ticket($user, TicketType::USER, 'Points not added after check-in');

        $this->actingAs($this->admin)
            ->get("/cms/support-desk/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/support/show', false)
                ->where('ticket.number', "T-{$ticket->id}")
                ->where('ticket.phone', $user->phone)
                ->has('ticket.messages', 1)
                ->where('ticket.messages.0.author', 'Sujan Karki'));

        $this->travel(2)->hours();

        $this->actingAs($this->admin)
            ->post("/cms/support-desk/{$ticket->id}/reply", ['body' => 'Added 20 points, sorry about that.'])
            ->assertSessionHas('success', 'Reply sent.');

        $ticket->refresh();
        $this->assertNotNull($ticket->first_replied_at);
        $this->assertSame(TicketStatus::OPEN, $ticket->status);
        $this->assertCount(1, $this->pushes);
        $this->assertSame('Najik support', $this->pushes[0]['title']);

        $this->actingAs($this->admin)
            ->get('/cms/support-desk')
            ->assertInertia(fn (Assert $page) => $page->where('stats.avg_first_reply_hours', 2));

        $this->actingAs($this->admin)->post("/cms/support-desk/{$ticket->id}/reply", [])->assertSessionHasErrors('body');
    }

    public function test_reply_and_resolve_then_reopen(): void
    {
        $ticket = $this->ticket($this->customer(), TicketType::USER, 'QR poster request');

        $this->actingAs($this->admin)
            ->post("/cms/support-desk/{$ticket->id}/reply", ['body' => 'Posted today.', 'resolve' => true])
            ->assertSessionHas('success', 'Reply sent and ticket resolved.');

        $ticket->refresh();
        $this->assertSame(TicketStatus::RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->actingAs($this->admin)
            ->patch("/cms/support-desk/{$ticket->id}/status", ['status' => 'open'])
            ->assertSessionHas('success', 'Ticket reopened.');

        $ticket->refresh();
        $this->assertSame(TicketStatus::OPEN, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertTrue($ticket->due_at->isFuture());

        $this->actingAs($this->admin)->patch("/cms/support-desk/{$ticket->id}/status", ['status' => 'resolved'])->assertSessionHas('success', 'Ticket resolved.');
        $this->actingAs($this->admin)->patch("/cms/support-desk/{$ticket->id}/status", ['status' => 'closed'])->assertSessionHasErrors('status');
    }

    private function ticket(User $user, TicketType $type, string $subject): SupportTicket
    {
        return app(SupportService::class)->open($user, $type, $subject, 'Details for '.$subject);
    }
}
