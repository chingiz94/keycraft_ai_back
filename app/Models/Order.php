<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'build_id', 'status', 'total', 'idempotency_key', 'request_hash'];

    protected $hidden = ['request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'total' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
