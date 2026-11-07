<?php

namespace App\Services;

class CompatibilityService
{
    private const TYPES = ['case', 'pcb', 'plate', 'stabilizer', 'switch', 'keycap'];

    /**
     * The same rules validate saved builds and the untrusted answer of the AI.
     * Prices and quantities are integers; total is in minor currency units.
     */
    public function analyze(iterable $items): array
    {
        $parts = [];
        $issues = [];
        $unverified = [];
        $total = 0;

        foreach ($items as $index => $item) {
            $component = data_get($item, 'component');
            $quantity = data_get($item, 'quantity');
            $path = "items.{$index}";

            if (! $component) {
                $issues[] = "{$path}: компонент не найден.";

                continue;
            }

            $type = data_get($component, 'type');
            if (! in_array($type, self::TYPES, true)) {
                $issues[] = "{$path}: неизвестный тип компонента.";

                continue;
            }
            if (! is_int($quantity) || $quantity < 1) {
                $issues[] = "{$path}: количество должно быть положительным целым числом.";

                continue;
            }
            if (isset($parts[$type])) {
                $issues[] = "Для типа {$type} допускается одна позиция в сборке.";

                continue;
            }
            if ($type !== 'switch' && $quantity !== 1) {
                $issues[] = "Для типа {$type} требуется ровно один комплект.";
            }

            $parts[$type] = ['component' => $component, 'quantity' => $quantity];
            $price = data_get($component, 'price');
            if (! is_int($price) || $price < 0) {
                $unverified[] = "{$type}: не указана корректная цена.";
            } else {
                $total += $price * $quantity;
            }
        }

        $missing = array_values(array_diff(self::TYPES, array_keys($parts)));
        foreach ($missing as $type) {
            $issues[] = "Отсутствует обязательный компонент: {$type}.";
        }

        $read = function (string $type, string $field, string $kind = 'string') use ($parts, &$unverified): mixed {
            if (! isset($parts[$type])) {
                return null;
            }
            $value = data_get($parts[$type]['component'], $field);
            $valid = match ($kind) {
                'int' => is_int($value) && $value > 0,
                'map' => is_array($value) && ! array_is_list($value) && $value !== []
                    && count(array_filter($value, fn ($count) => ! is_int($count) || $count < 0)) === 0,
                default => is_string($value) && trim($value) !== '',
            };
            if (! $valid) {
                $unverified[] = "{$type}: характеристика {$field} отсутствует или некорректна.";

                return null;
            }

            return $value;
        };

        $same = function (array $types, string $field) use ($read, &$issues): void {
            $values = [];
            foreach ($types as $type) {
                $value = $read($type, $field);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
            if (count(array_unique($values, SORT_STRING)) > 1) {
                $issues[] = 'Не совпадает '.$field.' у компонентов: '.implode(', ', $types).'.';
            }
        };

        $same(['case', 'pcb', 'plate'], 'format');
        $same(['case', 'pcb', 'plate'], 'specs.mount');
        $same(['pcb', 'plate', 'switch'], 'specs.switch_type');
        $same(['switch', 'keycap'], 'specs.stem');
        $same(['pcb', 'keycap'], 'specs.layout');

        $keys = $read('pcb', 'specs.key_count', 'int');
        $keycaps = $read('keycap', 'specs.key_count', 'int');
        if ($keys !== null && isset($parts['switch']) && $parts['switch']['quantity'] < $keys) {
            $issues[] = "Недостаточно свитчей: требуется не менее {$keys}.";
        }
        if ($keys !== null && $keycaps !== null && $keycaps < $keys) {
            $issues[] = "Набор кейкапов не покрывает раскладку: требуется не менее {$keys} клавиш.";
        }

        $requiredStabilizers = $read('pcb', 'specs.stabilizers', 'map');
        $availableStabilizers = $read('stabilizer', 'specs.stabilizers', 'map');
        if ($requiredStabilizers !== null && $availableStabilizers !== null) {
            foreach ($requiredStabilizers as $size => $count) {
                if (($availableStabilizers[$size] ?? 0) < $count) {
                    $issues[] = "Недостаточно стабилизаторов {$size}: требуется {$count}.";
                }
            }
        }

        return [
            'compatible' => $issues === [] && $unverified === [],
            'complete' => $missing === [],
            'issues' => array_values(array_unique($issues)),
            'unverified' => array_values(array_unique($unverified)),
            'total' => $total,
        ];
    }
}
