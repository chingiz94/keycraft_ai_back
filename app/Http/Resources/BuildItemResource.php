<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'component_id' => $this->component_id, 'quantity' => $this->quantity, 'component' => new ComponentResource($this->whenLoaded('component'))];
    }
}
