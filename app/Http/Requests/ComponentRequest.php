<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ComponentRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['name' => "$required|string|max:150", 'type' => [$required, Rule::in(['case', 'pcb', 'plate', 'stabilizer', 'switch', 'keycap'])],
            'price' => "$required|integer|min:1|max:100000000", 'stock' => "$required|integer|min:0|max:1000000", 'format' => 'nullable|string|max:20',
            'specs' => "$required|array:mount,switch_type,stem,layout,key_count,stabilizers",
            'specs.mount' => 'sometimes|string|max:40', 'specs.switch_type' => 'sometimes|string|max:40',
            'specs.stem' => 'sometimes|string|max:40', 'specs.layout' => 'sometimes|string|max:40',
            'specs.key_count' => 'sometimes|integer|min:1|max:150', 'specs.stabilizers' => 'sometimes|array',
            'specs.stabilizers.*' => 'integer|min:0|max:100'];
    }
}
