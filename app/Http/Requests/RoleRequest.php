<?php

namespace App\Http\Requests;

class RoleRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['role' => 'required|in:buyer,admin'];
    }
}
