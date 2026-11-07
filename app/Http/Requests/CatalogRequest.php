<?php

namespace App\Http\Requests;

class CatalogRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['search' => 'sometimes|string|max:100', 'type' => 'sometimes|in:case,pcb,plate,stabilizer,switch,keycap', 'format' => 'sometimes|string|max:30', 'min_price' => 'sometimes|integer|min:0', 'max_price' => 'sometimes|integer|min:0'.($this->filled('min_price') ? '|gte:min_price' : ''), 'in_stock' => 'sometimes|boolean', 'sort' => 'sometimes|in:name,price,created_at', 'order' => 'sometimes|in:asc,desc', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100'];
    }
}
