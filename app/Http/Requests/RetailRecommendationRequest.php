<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class RetailRecommendationRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'budget' => 'required|integer|min:1|max:100000000',
            'preferences' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
