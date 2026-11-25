<?php

namespace App\Http\Resources;

use App\Models\FreeWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FreeWindow */
class FreeWindowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preset' => $this->preset,
            'emoji' => FreeWindow::PRESETS[$this->preset]['emoji'] ?? '🕒',
            'title' => $this->title,
            'subtitle' => $this->subtitle(),
            'weekdays' => $this->weekdays,
            'from' => substr($this->from_time, 0, 5),
            'to' => substr($this->to_time, 0, 5),
            'enabled' => $this->enabled,
        ];
    }
}
