<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function reserve(Order $order, array $line): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($line['product']->id);
        $quantity = (int) $line['quantity'];

        if ($product->stock < $quantity) {
            throw ValidationException::withMessages([
                'cart' => "Only {$product->stock} units of {$product->name} remain in stock.",
            ]);
        }

        $before = $product->stock;
        $product->decrement('stock', $quantity);
        InventoryMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'order_reserved',
            'quantity' => -$quantity,
            'before_stock' => $before,
            'after_stock' => $before - $quantity,
            'note' => "Reserved for {$order->number}",
        ]);
    }

    public function restore(Order $order, string $reason = 'Order cancelled'): void
    {
        if ($order->inventoryMovements()->where('type', 'order_restored')->exists()) {
            return;
        }

        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            $product = Product::query()->lockForUpdate()->find($item->product_id);
            if (! $product) {
                continue;
            }
            $before = $product->stock;
            $product->increment('stock', $item->quantity);
            InventoryMovement::create([
                'product_id' => $product->id,
                'order_id' => $order->id,
                'type' => 'order_restored',
                'quantity' => $item->quantity,
                'before_stock' => $before,
                'after_stock' => $before + $item->quantity,
                'note' => $reason,
            ]);
        }
    }

    public function adjust(Product $product, int $newStock, ?User $user = null, string $note = 'Admin stock adjustment'): void
    {
        DB::transaction(function () use ($product, $newStock, $user, $note): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $before = $lockedProduct->stock;

            if ($before === $newStock) {
                return;
            }

            $lockedProduct->update(['stock' => $newStock]);
            InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user?->id,
                'type' => 'manual_adjustment',
                'quantity' => $newStock - $before,
                'before_stock' => $before,
                'after_stock' => $newStock,
                'note' => $note,
            ]);
        });

        $product->refresh();
    }
}
