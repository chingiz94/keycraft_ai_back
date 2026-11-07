<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'required' => 'Поле :attribute обязательно.', 'integer' => 'Поле :attribute должно быть целым числом.',
            'min' => 'Поле :attribute меньше допустимого минимума :min.', 'max' => 'Поле :attribute превышает максимум :max.',
            'email' => 'Укажите корректный email.', 'unique' => 'Значение :attribute уже используется.',
            'confirmed' => 'Подтверждение пароля не совпадает.', 'exists' => 'Связанная запись не найдена.',
            'in' => 'Недопустимое значение :attribute.', 'string' => 'Поле :attribute должно быть строкой.',
            'array' => 'Поле :attribute должно быть массивом.', 'url' => 'Укажите корректную HTTPS ссылку.',
            'date' => 'Укажите корректную дату.', 'distinct' => 'Повторяющиеся значения запрещены.',
            'prohibited' => 'Поле :attribute запрещено.', 'required_with' => 'Поле :attribute обязательно.',
        ];
    }
}
