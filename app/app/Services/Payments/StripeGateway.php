<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeGateway
{
    public function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function checkout(Order $order): string
    {
        $response = Http::withToken(config('services.stripe.secret'))
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'expires_at' => $order->expires_at?->timestamp,
                'success_url' => route('checkout.gateway.success', ['provider' => 'stripe', 'order' => $order]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.gateway.cancel', ['provider' => 'stripe', 'order' => $order]),
                'customer_email' => $order->customer_email,
                'client_reference_id' => $order->number,
                'metadata[order_number]' => $order->number,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($order->currency),
                'line_items[0][price_data][unit_amount]' => (int) round((float) $order->total * 100),
                'line_items[0][price_data][product_data][name]' => "SNH order {$order->number}",
            ]);

        if ($response->failed() || ! $response->json('url')) {
            throw new RuntimeException($response->json('error.message', 'Stripe could not start the payment session.'));
        }

        $order->update(['payment_reference' => $response->json('id')]);

        return $response->json('url');
    }

    public function confirm(string $sessionId, Order $order): bool
    {
        if (! $order->payment_reference || ! hash_equals($order->payment_reference, $sessionId)) {
            return false;
        }

        $response = Http::withToken(config('services.stripe.secret'))
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        return $response->successful()
            && $response->json('client_reference_id') === $order->number
            && $response->json('payment_status') === 'paid'
            && (int) $response->json('amount_total') === (int) round((float) $order->total * 100)
            && strtolower((string) $response->json('currency')) === strtolower($order->currency);
    }

    public function expire(Order $order): bool
    {
        if (! $order->payment_reference) {
            return false;
        }

        $response = Http::withToken(config('services.stripe.secret'))
            ->asForm()
            ->post("https://api.stripe.com/v1/checkout/sessions/{$order->payment_reference}/expire");

        return $response->successful() && $response->json('status') === 'expired';
    }

    public function webhookEvent(Request $request): ?array
    {
        $secret = config('services.stripe.webhook_secret');
        $header = $request->header('Stripe-Signature', '');
        if (! $secret || ! $header) {
            return null;
        }

        $parts = collect(explode(',', $header))->mapWithKeys(function (string $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            return $key && $value ? [$key => $value] : [];
        });
        $timestamp = (int) $parts->get('t');
        $signature = $parts->get('v1');
        if (! $timestamp || ! $signature || abs(time() - $timestamp) > 300) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return $request->json()->all();
    }
}
