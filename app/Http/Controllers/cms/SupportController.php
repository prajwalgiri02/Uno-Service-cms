<?php

namespace App\Http\Controllers\cms;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportService $support,
    ) {}

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['sometimes', 'nullable', Rule::enum(TicketType::class)],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $status = $data['status'] ?? TicketStatus::OPEN->value;
        $search = trim($data['search'] ?? '');

        $tickets = SupportTicket::query()
            ->with('user:id,name,phone')
            ->where('status', $status)
            ->when($data['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhere('id', ltrim(strtoupper($search), 'T-'))))
            ->orderBy($status === TicketStatus::OPEN->value ? 'due_at' : 'resolved_at', $status === TicketStatus::OPEN->value ? 'asc' : 'desc')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (SupportTicket $ticket) => $this->row($ticket));

        $replied = SupportTicket::query()->whereNotNull('first_replied_at')->where('created_at', '>=', now()->subDays(30))->get(['created_at', 'first_replied_at']);

        return Inertia::render('cms/support/index', [
            'tickets' => $tickets,
            'filters' => ['type' => $data['type'] ?? '', 'status' => $status, 'search' => $search],
            'stats' => [
                'open' => SupportTicket::query()->where('status', TicketStatus::OPEN)->count(),
                'new_today' => SupportTicket::query()->where('created_at', '>=', today())->count(),
                'due_soon' => SupportTicket::query()->where('status', TicketStatus::OPEN)->where('due_at', '<=', now()->addHours(4))->count(),
                'avg_first_reply_hours' => $replied->isEmpty() ? null : round($replied->avg(fn (SupportTicket $t) => $t->created_at->diffInMinutes($t->first_replied_at)) / 60, 1),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['user:id,name,phone', 'messages.user:id,name']);

        return Inertia::render('cms/support/show', [
            'ticket' => [
                ...$this->row($ticket),
                'phone' => $ticket->user?->phone,
                'user_id' => $ticket->user_id,
                'messages' => $ticket->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'from_admin' => $m->from_admin,
                    'author' => $m->from_admin ? ($m->user?->name ?? 'Najik support') : ($ticket->user?->displayName() ?? 'User'),
                    'body' => $m->body,
                    'at' => $m->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
            'resolve' => ['sometimes', 'boolean'],
        ]);

        $this->support->reply($ticket, $request->user(), $data['body'], true);

        if ($request->boolean('resolve')) {
            $this->support->resolve($ticket);
        }

        return back()->with('success', $request->boolean('resolve') ? 'Reply sent and ticket resolved.' : 'Reply sent.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);

        $data['status'] === TicketStatus::RESOLVED->value ? $this->support->resolve($ticket) : $this->support->reopen($ticket);

        return back()->with('success', $data['status'] === TicketStatus::RESOLVED->value ? 'Ticket resolved.' : 'Ticket reopened.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number(),
            'from' => $ticket->user?->displayName(),
            'type' => $ticket->type->value,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'opened_at' => $ticket->created_at->toIso8601String(),
            'due_at' => $ticket->due_at->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
        ];
    }
}
