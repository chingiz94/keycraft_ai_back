<?php

namespace App\Http\Requests;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', $this->bcryptPasswordRule()], 'role' => 'prohibited'];
    }
}
