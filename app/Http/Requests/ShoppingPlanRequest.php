<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ShoppingPlanRequest extends ApiRequest
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
            'kit_id' => 'present|nullable|string|max:80',
            'switch_id' => 'present|nullable|string|max:80',
            'keycap_id' => 'present|nullable|string|max:80',
            'price' => 'prohibited',
            'total' => 'prohibited',
            'user_id' => 'prohibited',
        ];
    }
}
