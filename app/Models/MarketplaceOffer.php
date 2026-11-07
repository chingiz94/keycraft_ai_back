<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOffer extends Model
{
    use HasFactory;

    protected $fillable = ['component_id', 'marketplace', 'url', 'price', 'price_updated_at'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'price_updated_at' => 'datetime'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
