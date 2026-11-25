<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'subject', 'status', 'due_at', 'first_replied_at', 'resolved_at'])]
class SupportTicket extends Model
{
    public const RESPONSE_HOURS = 24;

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'due_at' => 'datetime',
            'first_replied_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest()->orderBy('id');
    }

    public function number(): string
    {
        return 'T-'.$this->id;
    }

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::OPEN;
    }
}
