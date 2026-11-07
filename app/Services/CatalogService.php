<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Component;
use App\Models\MarketplaceOffer;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    public function list(array $filters)
    {
        return Component::query()->withCount('offers')
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.$v.'%'))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['format'] ?? null, fn ($q, $v) => $q->where('format', $v))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->when(isset($filters['in_stock']), fn ($q) => $q->where('stock', $filters['in_stock'] ? '>' : '=', 0))
            ->orderBy($filters['sort'] ?? 'name', $filters['order'] ?? 'asc')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)->withQueryString();
    }

    public function save(?Component $component, array $data): Component
    {
        return DB::transaction(function () use ($component, $data) {
            if (! $component) {
                return Component::create($data);
            }
            $component = Component::whereKey($component->id)->lockForUpdate()->firstOrFail();
            $component->update($data);

            return $component;
        });
    }

    public function delete(Component $component): void
    {
        DB::transaction(function () use ($component) {
            $component = Component::whereKey($component->id)->lockForUpdate()->firstOrFail();
            if ($component->buildItems()->exists() || $component->orderItems()->exists()) {
                throw new BusinessException('Компонент используется в сборке или заказе');
            }
            $component->offers()->delete();
            $component->delete();
        });
    }

    public function saveOffer(?MarketplaceOffer $offer, array $data): MarketplaceOffer
    {
        if (! $offer) {
            return MarketplaceOffer::create($data);
        }
        $offer->update($data);

        return $offer;
    }
}
