<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'shop_id', 'reason', 'note', 'status', 'resolution', 'resolution_note', 'resolved_by', 'resolved_at'])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolution' => ReportResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
