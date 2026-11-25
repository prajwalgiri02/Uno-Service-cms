<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shop_id', 'name', 'for_who', 'minutes', 'price_min', 'price_max', 'walk_ins', 'photo_path', 'sort_order'])]
class ShopService extends Model
{
    protected function casts(): array
    {
        return [
            'walk_ins' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
