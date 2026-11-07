<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'price', 'stock', 'format', 'specs'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer', 'specs' => 'array'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MarketplaceOffer::class);
    }

    public function buildItems(): HasMany
    {
        return $this->hasMany(BuildItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['format'] ?? null, fn (Builder $q, string $format) => $q->where('format', $format))
            ->when(isset($filters['min_price']), fn (Builder $q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn (Builder $q) => $q->where('price', '<=', $filters['max_price']))
            ->when(isset($filters['in_stock']), fn (Builder $q) => filter_var($filters['in_stock'], FILTER_VALIDATE_BOOL) ? $q->where('stock', '>', 0) : $q->where('stock', 0));
    }
}
