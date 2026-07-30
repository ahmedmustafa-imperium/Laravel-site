<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesCommerceData;
use Tests\TestCase;

class AuthenticationAndAdminTest extends TestCase
{
    use CreatesCommerceData, RefreshDatabase;

    public function test_a_customer_can_register_and_log_in(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'Mariam',
            'last_name' => 'Ali',
            'email' => 'mariam@example.com',
            'phone' => '+971500000002',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms' => '1',
        ])->assertRedirect(route('account.index'));

        $user = User::where('email', 'mariam@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'mariam@example.com',
            'password' => 'SecurePass123!',
        ])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_routes_are_protected_and_stock_edits_are_audited(): void
    {
        $product = $this->createProduct();
        $customer = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'stock' => 16,
            'low_stock_threshold' => 2,
            'image' => $product->image,
            'gallery' => '',
            'variants_json' => '',
            'sort_order' => 1,
            'is_featured' => '1',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(16, $product->fresh()->stock);
        $this->assertDatabaseHas(InventoryMovement::class, [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => 'manual_adjustment',
            'quantity' => 6,
        ]);
    }
}
