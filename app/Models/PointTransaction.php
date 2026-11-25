<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'check_in_id', 'points', 'reason', 'created_by', 'note'])]
class PointTransaction extends Model
{
    public const REASON_CHECK_IN = 'check_in';

    public const REASON_ADJUSTMENT = 'adjustment';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
