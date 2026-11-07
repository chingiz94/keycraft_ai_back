<?php

namespace App\Http\Requests;

class OfferRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['component_id' => "$r|integer|exists:components,id", 'marketplace' => "$r|string|max:100", 'url' => "$r|url:https|max:2000", 'price' => "$r|integer|min:1|max:100000000", 'price_updated_at' => "$r|date|before_or_equal:now"];
    }
}
