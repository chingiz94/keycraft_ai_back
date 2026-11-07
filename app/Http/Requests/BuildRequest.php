<?php

namespace App\Http\Requests;

class BuildRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['name' => "$r|string|max:150", 'budget' => "$r|integer|min:1|max:100000000", 'user_id' => 'prohibited'];
    }
}
