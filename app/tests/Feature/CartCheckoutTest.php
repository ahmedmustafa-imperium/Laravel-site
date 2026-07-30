<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\CreatesCommerceData;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use CreatesCommerceData, RefreshDatabase;

    public function test_coupon_checkout_creates_an_order_reserves_stock_and_queues_email(): void
    {
        Mail::fake();
        $product = $this->createProduct();
        $coupon = $this->coupon();

        $this->from(route('products.show', $product))
            ->post(route('cart.store', $product), ['quantity' => 2])
            ->assertRedirect(route('products.show', $product));

        $this->from(route('cart.index'))
            ->post(route('cart.coupon'), ['coupon' => 'welcome10'])
            ->assertRedirect(route('cart.index'));

        $this->get(route('checkout.index'))->assertOk();
        $response = $this->post(route('checkout.store'), array_merge($this->checkoutData(), [
            'checkout_token' => session('checkout_token'),
        ]));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $order = Order::with('items')->sole();
        $this->assertSame('processing', $order->status);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('120.00', $order->subtotal);
        $this->assertSame('12.00', $order->discount);
        $this->assertSame('0.00', $order->shipping);
        $this->assertSame('108.00', $order->total);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertDatabaseHas(InventoryMovement::class, [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'order_reserved',
            'quantity' => -2,
        ]);
        $response->assertSessionMissing('cart');
        Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->order->is($order));
    }

    public function test_cancelling_a_pending_gateway_order_restores_inventory_and_coupon_usage(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['status' => 'expired'])]);
        $product = $this->createProduct();
        $coupon = $this->coupon();

        $this->post(route('cart.store', $product), ['quantity' => 3]);
        $this->post(route('cart.coupon'), ['coupon' => $coupon->code]);

        $order = app(OrderService::class)->place(array_merge($this->checkoutData(), ['payment_method' => 'stripe']));
        $order->update(['payment_reference' => 'cs_test_cancel']);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->times_used);

        $this->withSession(['pending_order' => $order->number])
            ->get(route('checkout.gateway.cancel', ['provider' => 'stripe', 'order' => $order]))
            ->assertRedirect(route('cart.index'));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->times_used);
        $this->assertDatabaseHas(InventoryMovement::class, [
            'order_id' => $order->id,
            'type' => 'order_restored',
            'quantity' => 3,
        ]);
    }

    public function test_payment_callbacks_cannot_control_an_order_without_the_checkout_session(): void
    {
        $product = $this->createProduct();
        $this->post(route('cart.store', $product), ['quantity' => 1]);
        $order = app(OrderService::class)->place(array_merge($this->checkoutData(), ['payment_method' => 'stripe']));

        $this->get(route('checkout.gateway.cancel', ['provider' => 'stripe', 'order' => $order]))
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_checkout_tokens_prevent_duplicate_orders(): void
    {
        $product = $this->createProduct();
        $this->post(route('cart.store', $product), ['quantity' => 2]);
        $token = (string) Str::uuid();
        $data = array_merge($this->checkoutData(), ['checkout_token' => $token, 'payment_method' => 'stripe']);

        $first = app(OrderService::class)->place($data);
        $second = app(OrderService::class)->place($data);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_expired_online_orders_release_stock_and_coupon_usage(): void
    {
        $product = $this->createProduct();
        $coupon = $this->coupon();
        $this->post(route('cart.store', $product), ['quantity' => 2]);
        $this->post(route('cart.coupon'), ['coupon' => $coupon->code]);

        $order = app(OrderService::class)->place(array_merge($this->checkoutData(), [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'stripe',
        ]));
        $order->update(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    private function coupon(): Coupon
    {
        return Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => 10,
            'minimum_amount' => 0,
            'times_used' => 0,
            'is_active' => true,
        ]);
    }

    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Amina Noor',
            'customer_email' => 'amina@example.com',
            'customer_phone' => '+971500000003',
            'address_line_1' => 'Warehouse 12, Al Quoz',
            'address_line_2' => null,
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'postal_code' => null,
            'notes' => 'Call on arrival.',
            'payment_method' => 'cod',
            'terms' => '1',
        ];
    }
}
