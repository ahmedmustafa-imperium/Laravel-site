<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class CartManager
{
    public const FREE_SHIPPING_THRESHOLD = 99.00;

    public const STANDARD_SHIPPING = 15.00;

    public function add(Product $product, int $quantity = 1, ?string $variant = null): void
    {
        if (! $product->is_active || $product->stock < 1) {
            throw ValidationException::withMessages(['product' => 'This product is currently unavailable.']);
        }

        $options = collect($product->variants ?? []);
        if ($options->isNotEmpty() && ! $variant) {
            throw ValidationException::withMessages(['variant' => 'Please choose a product option.']);
        }

        if ($variant && ! $options->contains(fn (array $option) => ($option['name'] ?? null) === $variant)) {
            throw ValidationException::withMessages(['variant' => 'Please choose a valid product option.']);
        }

        $key = $this->key($product->id, $variant);
        $cart = Session::get('cart', []);
        $current = (int) ($cart[$key]['quantity'] ?? 0);
        $cart[$key] = [
            'product_id' => $product->id,
            'variant' => $variant,
            'quantity' => min($product->stock, $current + max(1, $quantity)),
        ];
        Session::put('cart', $cart);
    }

    public function update(string $key, int $quantity): void
    {
        $cart = Session::get('cart', []);
        if (! isset($cart[$key])) {
            return;
        }

        if ($quantity < 1) {
            $this->remove($key);

            return;
        }

        $product = Product::active()->find($cart[$key]['product_id']);
        if (! $product) {
            $this->remove($key);

            return;
        }

        $cart[$key]['quantity'] = min($product->stock, $quantity);
        Session::put('cart', $cart);
    }

    public function remove(string $key): void
    {
        $cart = Session::get('cart', []);
        unset($cart[$key]);
        Session::put('cart', $cart);
    }

    public function clear(): void
    {
        Session::forget(['cart', 'coupon_code']);
    }

    public function count(): int
    {
        return collect(Session::get('cart', []))->sum('quantity');
    }

    public function items(): Collection
    {
        $cart = Session::get('cart', []);
        if ($cart === []) {
            return collect();
        }

        $products = Product::active()->whereIn('id', collect($cart)->pluck('product_id'))->get()->keyBy('id');

        return collect($cart)->map(function (array $line, string $key) use ($products) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                return null;
            }

            $quantity = min((int) $line['quantity'], $product->stock);
            if ($quantity < 1) {
                return null;
            }
            $price = $product->priceForVariant($line['variant'] ?? null);

            return [
                'key' => $key,
                'product' => $product,
                'variant' => $line['variant'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'line_total' => round($price * $quantity, 2),
            ];
        })->filter()->values();
    }

    public function summary(): array
    {
        $items = $this->items();
        $subtotal = round((float) $items->sum('line_total'), 2);
        $coupon = $this->couponFor($subtotal);
        $discount = $coupon?->discountFor($subtotal) ?? 0.0;
        $afterDiscount = max(0, $subtotal - $discount);
        $shipping = $subtotal === 0 || $afterDiscount >= self::FREE_SHIPPING_THRESHOLD ? 0.0 : self::STANDARD_SHIPPING;

        return [
            'items' => $items,
            'count' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'coupon' => $coupon,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => round($afterDiscount + $shipping, 2),
            'free_shipping_remaining' => round(max(0, self::FREE_SHIPPING_THRESHOLD - $afterDiscount), 2),
        ];
    }

    public function applyCoupon(string $code): Coupon
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();
        $subtotal = (float) $this->items()->sum('line_total');

        if (! $coupon || ! $coupon->isValidFor($subtotal)) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is invalid, expired, or does not meet the minimum order amount.']);
        }

        Session::put('coupon_code', $coupon->code);

        return $coupon;
    }

    public function removeCoupon(): void
    {
        Session::forget('coupon_code');
    }

    private function couponFor(float $subtotal): ?Coupon
    {
        $code = Session::get('coupon_code');
        if (! $code) {
            return null;
        }
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon || ! $coupon->isValidFor($subtotal)) {
            Session::forget('coupon_code');

            return null;
        }

        return $coupon;
    }

    private function key(int $productId, ?string $variant): string
    {
        return $productId.'-'.substr(sha1($variant ?: 'default'), 0, 10);
    }
}
