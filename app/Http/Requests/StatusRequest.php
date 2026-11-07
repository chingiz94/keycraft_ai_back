<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class StatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(OrderStatus::class)]];
    }
}
