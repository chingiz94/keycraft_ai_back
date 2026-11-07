<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function bcryptPasswordRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                $fail('Пароль должен занимать не более 72 байт UTF-8 и не содержать нулевой символ.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'enum' => 'Недопустимое значение :attribute.',
            'regex' => 'Поле :attribute имеет неверный формат.',
            'boolean' => 'Поле :attribute должно быть логическим значением.',
            'before_or_equal' => 'Поле :attribute не может быть позже :date.',
            'required' => 'Поле :attribute обязательно.', 'integer' => 'Поле :attribute должно быть целым числом.',
            'min' => 'Поле :attribute меньше допустимого минимума :min.', 'max' => 'Поле :attribute превышает максимум :max.',
            'email' => 'Укажите корректный email.', 'unique' => 'Значение :attribute уже используется.',
            'confirmed' => 'Подтверждение пароля не совпадает.', 'exists' => 'Связанная запись не найдена.',
            'in' => 'Недопустимое значение :attribute.', 'string' => 'Поле :attribute должно быть строкой.',
            'array' => 'Поле :attribute должно быть массивом.', 'url' => 'Укажите корректную HTTPS ссылку.',
            'date' => 'Укажите корректную дату.', 'distinct' => 'Повторяющиеся значения запрещены.',
            'prohibited' => 'Поле :attribute запрещено.', 'required_with' => 'Поле :attribute обязательно.',
            'gte' => 'Значение :attribute должно быть не меньше :value.',
        ];
    }
}
