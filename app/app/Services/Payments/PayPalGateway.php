<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalGateway
{
    public function configured(): bool
    {
        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret'));
    }

    public function checkout(Order $order): string
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->number,
                    'description' => "SNH order {$order->number}",
                    'amount' => ['currency_code' => $order->currency, 'value' => number_format((float) $order->total, 2, '.', '')],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'brand_name' => config('app.name'),
                            'user_action' => 'PAY_NOW',
                            'return_url' => route('checkout.gateway.success', ['provider' => 'paypal', 'order' => $order]),
                            'cancel_url' => route('checkout.gateway.cancel', ['provider' => 'paypal', 'order' => $order]),
                        ],
                    ],
                ],
            ]);

        $approve = collect($response->json('links', []))->firstWhere('rel', 'payer-action');
        if ($response->failed() || ! $approve) {
            throw new RuntimeException($response->json('message', 'PayPal could not start the payment session.'));
        }

        $order->update(['payment_reference' => $response->json('id')]);

        return $approve['href'];
    }

    public function capture(Order $order): bool
    {
        if (! $order->payment_reference) {
            return false;
        }
        $response = Http::withToken($this->accessToken())
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$order->number])
            ->post($this->baseUrl()."/v2/checkout/orders/{$order->payment_reference}/capture");

        $amount = number_format((float) $response->json('purchase_units.0.payments.captures.0.amount.value', -1), 2, '.', '');
        $currency = strtoupper((string) $response->json('purchase_units.0.payments.captures.0.amount.currency_code'));

        return $response->successful()
            && $response->json('id') === $order->payment_reference
            && $response->json('status') === 'COMPLETED'
            && $response->json('purchase_units.0.reference_id') === $order->number
            && $amount === number_format((float) $order->total, 2, '.', '')
            && $currency === strtoupper($order->currency);
    }

    public function validWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! $webhookId) {
            return false;
        }

        $response = Http::withToken($this->accessToken())->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $webhookId,
            'webhook_event' => $request->json()->all(),
        ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('PayPal credentials could not be authenticated.');
        }

        return $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
