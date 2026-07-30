<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'type', 'value', 'minimum_amount', 'usage_limit', 'times_used', 'starts_at', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isValidFor(float $subtotal): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->expires_at || $this->expires_at->isFuture())
            && (! $this->usage_limit || $this->times_used < $this->usage_limit)
            && $subtotal >= (float) $this->minimum_amount;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent' ? $subtotal * ((float) $this->value / 100) : (float) $this->value;

        return round(min($subtotal, $discount), 2);
    }
}
