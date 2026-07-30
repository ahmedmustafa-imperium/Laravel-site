<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-reservations';

    protected $description = 'Cancel expired online-payment orders and release their stock reservations';

    public function handle(OrderService $orders): int
    {
        $expired = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereIn('payment_method', ['stripe', 'paypal'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($pendingOrders) use ($orders, &$expired): void {
                foreach ($pendingOrders as $order) {
                    try {
                        if ($orders->expireReservation($order)) {
                            $expired++;
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->warn("Could not expire {$order->number}; continuing.");
                    }
                }
            });

        $this->info("Released {$expired} expired order reservation(s).");

        return self::SUCCESS;
    }
}
