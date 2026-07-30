<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private CartManager $cart, private InventoryService $inventory) {}

    public function place(array $data): Order
    {
        $checkoutToken = $data['checkout_token'] ?? null;
        if ($checkoutToken && $existing = Order::where('checkout_token', $checkoutToken)->first()) {
            return $existing->load('items');
        }

        $summary = $this->cart->summary();
        if ($summary['items']->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        try {
            return DB::transaction(function () use ($data, $summary, $checkoutToken) {
                $coupon = $summary['coupon']?->newQuery()->lockForUpdate()->find($summary['coupon']->id);
                if ($summary['coupon'] && ! $coupon) {
                    throw ValidationException::withMessages(['coupon' => 'That coupon is no longer available.']);
                }

                if ($coupon && ! $coupon->isValidFor($summary['subtotal'])) {
                    throw ValidationException::withMessages(['coupon' => 'That coupon is no longer available.']);
                }

                $discount = $coupon?->discountFor($summary['subtotal']) ?? 0.0;
                $afterDiscount = max(0, $summary['subtotal'] - $discount);
                $shipping = $summary['subtotal'] === 0.0 || $afterDiscount >= CartManager::FREE_SHIPPING_THRESHOLD
                    ? 0.0
                    : CartManager::STANDARD_SHIPPING;
                $total = round($afterDiscount + $shipping, 2);

                $order = Order::create([
                    'user_id' => auth()->id(),
                    'coupon_id' => $coupon?->id,
                    'number' => $this->number(),
                    'checkout_token' => $checkoutToken,
                    'status' => $data['payment_method'] === 'cod' ? 'processing' : 'pending',
                    'payment_method' => $data['payment_method'],
                    'payment_status' => 'pending',
                    'currency' => 'AED',
                    'subtotal' => $summary['subtotal'],
                    'discount' => $discount,
                    'shipping' => $shipping,
                    'total' => $total,
                    'coupon_code' => $coupon?->code,
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                    'customer_phone' => $data['customer_phone'],
                    'address_line_1' => $data['address_line_1'],
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'city' => $data['city'],
                    'emirate' => $data['emirate'],
                    'postal_code' => $data['postal_code'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'expires_at' => match ($data['payment_method']) {
                        'stripe' => now()->addMinutes((int) config('services.payments.stripe_reservation_minutes', 35)),
                        'paypal' => now()->addMinutes((int) config('services.payments.paypal_reservation_minutes', 360)),
                        default => null,
                    },
                ]);

                foreach ($summary['items']->sortBy(fn (array $line) => $line['product']->id) as $line) {
                    $order->items()->create([
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'sku' => $line['product']->sku,
                        'variant' => $line['variant'],
                        'unit_price' => $line['price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                        'image' => $line['product']->image,
                    ]);
                    $this->inventory->reserve($order, $line);
                }

                if ($coupon) {
                    $coupon->increment('times_used');
                }

                return $order->load('items');
            });
        } catch (QueryException $exception) {
            if ($checkoutToken && $existing = Order::where('checkout_token', $checkoutToken)->first()) {
                return $existing->load('items');
            }

            throw $exception;
        }
    }

    public function cancel(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === 'cancelled') {
                return;
            }

            if ($lockedOrder->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'A paid order cannot be cancelled. Refund it through the payment provider first.',
                ]);
            }

            $this->releaseReservation($lockedOrder, $reason);
        });

        $order->refresh();
    }

    public function expireReservation(Order $order): bool
    {
        $expired = DB::transaction(function () use ($order): bool {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $eligible = $lockedOrder->status === 'pending'
                && $lockedOrder->payment_status === 'pending'
                && in_array($lockedOrder->payment_method, ['stripe', 'paypal'], true)
                && $lockedOrder->expires_at?->isPast();

            if (! $eligible) {
                return false;
            }

            $this->releaseReservation($lockedOrder, 'Online payment window expired');

            return true;
        });

        $order->refresh();

        return $expired;
    }

    public function markPaid(Order $order, ?string $reference = null): string
    {
        $result = DB::transaction(function () use ($order, $reference): string {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->payment_status === 'paid') {
                return 'already_paid';
            }

            $attributes = [
                'payment_status' => 'paid',
                'payment_reference' => $reference ?: $lockedOrder->payment_reference,
                'paid_at' => now(),
                'expires_at' => null,
            ];

            if ($lockedOrder->status === 'cancelled') {
                $lockedOrder->update($attributes);

                return 'cancelled';
            }

            $lockedOrder->update(['status' => 'processing', ...$attributes]);

            return 'paid';
        });

        $order->refresh();

        return $result;
    }

    private function releaseReservation(Order $order, string $reason): void
    {
        $order->load('items');
        $this->inventory->restore($order, $reason);

        $coupon = $order->coupon()->lockForUpdate()->first();
        if ($coupon && $coupon->times_used > 0) {
            $coupon->decrement('times_used');
        }

        $order->update(['status' => 'cancelled', 'expires_at' => null]);
    }

    private function number(): string
    {
        do {
            $number = 'SNH-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('number', $number)->exists());

        return $number;
    }
}
