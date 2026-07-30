<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesCommerceData;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use CreatesCommerceData, RefreshDatabase;

    public function test_stripe_confirmation_requires_matching_reference_amount_and_currency(): void
    {
        $order = $this->onlineOrder('stripe');
        $order->update(['payment_reference' => 'cs_test_123']);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_123' => Http::response([
                'id' => 'cs_test_123',
                'client_reference_id' => $order->number,
                'payment_status' => 'paid',
                'amount_total' => (int) round((float) $order->total * 100),
                'currency' => 'aed',
            ]),
        ]);

        $this->assertTrue(app(StripeGateway::class)->confirm('cs_test_123', $order));
        $this->assertFalse(app(StripeGateway::class)->confirm('cs_wrong', $order));
    }

    public function test_signed_stripe_webhook_is_idempotent_and_matches_order_totals(): void
    {
        Mail::fake();
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $order = $this->onlineOrder('stripe');
        $order->update(['payment_reference' => 'cs_webhook_123']);
        $event = $this->stripeEvent($order, 'cs_webhook_123');

        $this->postSignedStripeEvent($event)->assertOk();
        $this->postSignedStripeEvent($event)->assertOk();

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertQueued(OrderPlaced::class, 1);
    }

    public function test_late_stripe_payment_is_recorded_without_reopening_cancelled_inventory(): void
    {
        Mail::fake();
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $product = $this->createProduct();
        $order = $this->onlineOrder('stripe', $product);
        $order->update(['payment_reference' => 'cs_late_123']);
        app(OrderService::class)->cancel($order, 'Test cancellation');

        $this->postSignedStripeEvent($this->stripeEvent($order, 'cs_late_123'))->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(10, $product->fresh()->stock);
        Mail::assertNothingQueued();
    }

    public function test_paypal_capture_matches_reference_amount_and_currency(): void
    {
        config(['services.paypal.client_id' => 'client', 'services.paypal.secret' => 'secret']);
        $order = $this->onlineOrder('paypal');
        $order->update(['payment_reference' => 'PAYPAL123']);

        Http::fake(function ($request) use ($order) {
            if (str_ends_with($request->url(), '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token']);
            }

            return Http::response([
                'id' => 'PAYPAL123',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->number,
                    'payments' => ['captures' => [[
                        'amount' => ['value' => number_format((float) $order->total, 2, '.', ''), 'currency_code' => 'AED'],
                    ]]],
                ]],
            ]);
        });

        $this->assertTrue(app(PayPalGateway::class)->capture($order));
    }

    private function onlineOrder(string $method, $product = null): Order
    {
        $product ??= $this->createProduct();
        $this->post(route('cart.store', $product), ['quantity' => 1]);

        return app(OrderService::class)->place([
            'customer_name' => 'Payment Test',
            'customer_email' => 'payments@example.com',
            'customer_phone' => '+971500000011',
            'address_line_1' => 'Warehouse 2',
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'payment_method' => $method,
        ]);
    }

    private function stripeEvent(Order $order, string $reference): array
    {
        return [
            'id' => 'evt_'.$reference,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $reference,
                'client_reference_id' => $order->number,
                'payment_status' => 'paid',
                'amount_total' => (int) round((float) $order->total * 100),
                'currency' => 'aed',
            ]],
        ];
    }

    private function postSignedStripeEvent(array $event)
    {
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }
}
