<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'format' => $this->format, 'price' => $this->price, 'stock' => $this->stock, 'specs' => $this->specs, 'offers' => OfferResource::collection($this->whenLoaded('offers'))];
    }
}
