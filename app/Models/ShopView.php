<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shop_id', 'user_id', 'created_at'])]
class ShopView extends Model
{
    public const UPDATED_AT = null;
}
