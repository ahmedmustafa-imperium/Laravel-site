<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'coupon_id', 'number', 'checkout_token', 'status', 'payment_method', 'payment_status', 'payment_reference',
    'currency', 'subtotal', 'discount', 'shipping', 'total', 'coupon_code', 'customer_name',
    'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'emirate',
    'postal_code', 'notes', 'paid_at', 'expires_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function address(): string
    {
        return collect([$this->address_line_1, $this->address_line_2, $this->city, $this->emirate, $this->postal_code])->filter()->implode(', ');
    }
}
