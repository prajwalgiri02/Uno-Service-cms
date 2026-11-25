<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = $this->user->name ? $this->shortName($this->user->name) : 'Najik user';

        return [
            'id' => $this->id,
            'name' => $name,
            'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
            'stars' => $this->stars,
            'text' => $this->text,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'needs_look' => $this->needsLook(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];

        return count($parts) > 1 ? $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $parts[0];
    }
}
