<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCommerceData;
use Tests\TestCase;

class AdminValidationTest extends TestCase
{
    use CreatesCommerceData, RefreshDatabase;

    public function test_product_variants_and_normalised_slugs_are_validated(): void
    {
        $product = $this->createProduct();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->productPayload($product, [
                'variants_json' => '{"name":"not-a-list"}',
            ]))
            ->assertSessionHasErrors('variants_json');

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->productPayload($product, [
            'variants_json' => '[{"name":"2 PKT","price":26.39},{"name":"6 PKT","price":60.90}]',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2 PKT', $product->fresh()->variants[0]['name']);

        $duplicate = $this->createProduct([
            'name' => 'Existing Product',
            'slug' => 'foo-bar',
            'sku' => 'SECOND-SKU',
            'category' => $product->category,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->productPayload($product, [
            'name' => 'Foo Bar',
            'slug' => 'Foo Bar',
        ]))->assertSessionHasErrors('slug');

        $this->assertSame('foo-bar', $duplicate->fresh()->slug);
    }

    public function test_products_with_variants_require_a_valid_option(): void
    {
        $product = $this->createProduct([
            'variants' => [['name' => '2 PKT', 'price' => 26.39]],
        ]);

        $this->from(route('products.show', $product))
            ->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors('variant');

        $this->post(route('cart.store', $product), ['quantity' => 1, 'variant' => '2 PKT'])
            ->assertSessionHasNoErrors();
    }

    public function test_category_parenting_rejects_cycles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'is_active' => true]);
        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true]);
        $otherParent = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $this->actingAs($admin)->put(route('admin.categories.update', $parent), [
            'parent_id' => $otherParent->id,
            'name' => $parent->name,
            'slug' => $parent->slug,
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_admin_order_transitions_require_consistent_payment_state_and_refund_confirmation(): void
    {
        $product = $this->createProduct();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('cart.store', $product), ['quantity' => 1]);
        $order = app(OrderService::class)->place($this->checkoutData());

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'completed',
            'payment_status' => 'pending',
        ])->assertSessionHasErrors('payment_status');

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'completed',
            'payment_status' => 'paid',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'completed',
            'payment_status' => 'refunded',
        ])->assertSessionHasErrors('refund_confirmed');

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'completed',
            'payment_status' => 'refunded',
            'refund_confirmed' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    private function productPayload($product, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'stock' => $product->stock,
            'low_stock_threshold' => $product->low_stock_threshold,
            'image' => $product->image,
            'gallery' => '',
            'variants_json' => '',
            'sort_order' => 1,
            'is_featured' => '1',
            'is_active' => '1',
        ], $overrides);
    }

    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Admin Flow',
            'customer_email' => 'orders@example.com',
            'customer_phone' => '+971500000010',
            'address_line_1' => 'Warehouse 1',
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'payment_method' => 'cod',
        ];
    }
}
