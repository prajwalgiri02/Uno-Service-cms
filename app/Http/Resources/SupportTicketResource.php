<?php

namespace App\Http\Resources;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportTicket */
class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number(),
            'type' => $this->type->value,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message) => [
                'id' => $message->id,
                'from_support' => $message->from_admin,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
