<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportService
{
    public function __construct(
        private readonly PushService $push,
    ) {}

    public function open(User $user, TicketType $type, string $subject, string $message): SupportTicket
    {
        return DB::transaction(function () use ($user, $type, $subject, $message) {
            $ticket = $user->supportTickets()->create([
                'type' => $type,
                'subject' => $subject,
                'status' => TicketStatus::OPEN,
                'due_at' => now()->addHours(SupportTicket::RESPONSE_HOURS),
            ]);

            $ticket->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => $message]);

            return $ticket;
        });
    }

    /**
     * A customer reply on a resolved ticket reopens it.
     */
    public function reply(SupportTicket $ticket, User $author, string $body, bool $fromAdmin): SupportMessage
    {
        $message = $ticket->messages()->create(['user_id' => $author->id, 'from_admin' => $fromAdmin, 'body' => $body]);

        if ($fromAdmin) {
            if ($ticket->first_replied_at === null) {
                $ticket->forceFill(['first_replied_at' => now()])->save();
            }

            $this->push->toUser($ticket->user, 'Najik support', str($body)->limit(120)->toString(), ['type' => 'support', 'ticket_id' => (string) $ticket->id]);
        } elseif (! $ticket->isOpen()) {
            $this->reopen($ticket);
        }

        return $message;
    }

    public function resolve(SupportTicket $ticket): void
    {
        $ticket->forceFill(['status' => TicketStatus::RESOLVED, 'resolved_at' => now()])->save();
    }

    public function reopen(SupportTicket $ticket): void
    {
        $ticket->forceFill(['status' => TicketStatus::OPEN, 'resolved_at' => null, 'due_at' => now()->addHours(SupportTicket::RESPONSE_HOURS)])->save();
    }
}
