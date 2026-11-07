<?php

namespace App\Http\Requests;

class BuildItemRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['component_id' => ($this->isMethod('post') ? 'required|integer|exists:components,id' : 'prohibited'), 'quantity' => 'required|integer|min:1|max:1000'];
    }
}
