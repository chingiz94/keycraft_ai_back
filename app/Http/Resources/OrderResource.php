<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'user_id' => $this->user_id, 'build_id' => $this->build_id, 'status' => $this->status->value, 'total' => $this->total, 'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => ['id' => $item->id, 'component_id' => $item->component_id, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'component' => new ComponentResource($item->component)]))];
    }
}
