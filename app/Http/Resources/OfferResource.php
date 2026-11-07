<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'component_id' => $this->component_id, 'marketplace' => $this->marketplace, 'url' => $this->url, 'price' => $this->price, 'price_updated_at' => $this->price_updated_at?->toIso8601String()];
    }
}
