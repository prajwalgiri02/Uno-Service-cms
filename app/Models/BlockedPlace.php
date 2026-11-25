<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'kind', 'label', 'latitude', 'longitude', 'radius_m', 'enabled'])]
class BlockedPlace extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
