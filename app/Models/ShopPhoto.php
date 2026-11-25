<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['shop_id', 'path', 'sort_order'])]
class ShopPhoto extends Model
{
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
