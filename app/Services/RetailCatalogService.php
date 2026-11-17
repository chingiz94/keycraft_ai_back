<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RetailCatalogService
{
    public function catalog(): array
    {
        $catalog = json_decode(file_get_contents(resource_path('data/retail-catalog.json')), true, 32, JSON_THROW_ON_ERROR);
        $catalog['products'] = array_map(function (array $product): array {
            $product['offers'] = collect($product['offers'])->sortBy(fn (array $offer): int => ($offer['availability'] === 'in_stock' ? 0 : 1000000000) + $offer['price'])->values()->all();
            $product['best_offer'] = $product['offers'][0];
            $product['stale'] = Carbon::parse($product['best_offer']['checked_at'])->addDays(7)->isPast();

            return $product;
        }, $catalog['products']);

        return $catalog;
    }

    public function quote(array $selection): array
    {
        $catalog = $this->catalog();
        $byId = collect($catalog['products'])->keyBy('id');
        $chosen = [];
        foreach (['kit_id' => 'kit', 'switch_id' => 'switch', 'keycap_id' => 'keycap'] as $field => $category) {
            $id = $selection[$field] ?? null;
            if ($id === null || $id === '') {
                continue;
            }
            $product = $byId->get($id);
            if (! $product || $product['category'] !== $category) {
                throw ValidationException::withMessages([$field => ['Выберите существующий товар правильного типа.']]);
            }
            $chosen[$category] = $product;
        }
        $rows = [];
        $warnings = [];
        $issues = [];
        $total = 0;
        $keyCount = $chosen['kit']['key_count'] ?? 0;
        foreach ($chosen as $category => $product) {
            $quantity = $category === 'switch' ? $keyCount : 1;
            $packs = $quantity > 0 ? (int) ceil($quantity / $product['pack_quantity']) : 0;
            $offer = $product['best_offer'];
            $subtotal = $packs * $offer['price'];
            $total += $subtotal;
            $rows[] = ['product' => $product, 'quantity' => $quantity, 'packs' => $packs, 'spare' => $packs * $product['pack_quantity'] - $quantity, 'offer' => $offer, 'subtotal' => $subtotal];
            if ($offer['availability'] !== 'in_stock') {
                $warnings[] = $product['name'].': у продавца отмечено отсутствие. Проверьте карточку или выберите другой товар.';
            }
            if ($product['stale']) {
                $warnings[] = $product['name'].': данным больше семи дней, цену и наличие нужно уточнить.';
            }
        }
        if (isset($chosen['kit'], $chosen['switch']) && $chosen['kit']['switch_type'] !== $chosen['switch']['switch_type']) {
            $issues[] = 'Тип свитчей не подходит к основе.';
        }
        if (isset($chosen['kit'], $chosen['keycap']) && $chosen['kit']['layout'] !== $chosen['keycap']['layout']) {
            $issues[] = 'Раскладка кейкапов не подходит к основе.';
        }
        if (isset($chosen['kit'], $chosen['keycap']) && ! in_array($chosen['kit']['format'], $chosen['keycap']['compatible_formats'] ?? [], true)) {
            $issues[] = 'Покрытие нестандартных клавиш этой основы набором кейкапов не подтверждено. Проверьте длину Shift и нижний ряд или выберите GPBT.';
        }
        $complete = count($chosen) === 3;

        return ['selection' => array_intersect_key($selection, array_flip(['kit_id', 'switch_id', 'keycap_id'])), 'items' => $rows, 'total' => $total, 'currency' => 'KZT', 'complete' => $complete, 'compatible' => $complete && $issues === [], 'issues' => $issues, 'warnings' => $warnings, 'key_count' => $keyCount, 'checked_at' => $catalog['checked_at'], 'notice' => $catalog['notice'], 'compatibility_sources' => $catalog['compatibility_sources']];
    }
}
