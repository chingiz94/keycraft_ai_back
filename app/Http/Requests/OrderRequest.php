<?php

namespace App\Http\Requests;

class OrderRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return ['build_id' => 'required|integer|min:1', 'expected_total' => 'required|integer|min:1|max:100000000000', 'idempotency_key' => 'required|string|max:100|regex:/^[A-Za-z0-9_-]+$/D'];
    }
}
