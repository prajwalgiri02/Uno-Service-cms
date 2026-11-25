<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'area' => $this->area,
            'language' => $this->language,
            'nudges_enabled' => $this->nudges_enabled,
            'paused_until' => $this->isPaused() ? $this->paused_until->toIso8601String() : null,
            'pings_per_day' => $this->pings_per_day,
            'points' => $this->points_balance,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'has_shop' => $this->whenLoaded('shop', fn () => $this->shop !== null),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
