<?php

namespace App\Http\Requests;

class RecommendationRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['budget' => 'required|integer|min:1|max:100000000', 'format' => 'required|string|max:30', 'preferences' => 'sometimes|string|max:1000'];
    }
}
