<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCommerceData;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use CreatesCommerceData, RefreshDatabase;

    public function test_the_storefront_catalog_and_search_are_rendered(): void
    {
        $product = $this->createProduct();

        $this->get('/')
            ->assertOk()
            ->assertSee('Shop by categories')
            ->assertSee($product->name);

        $this->get(route('categories.show', $product->category))
            ->assertOk()
            ->assertSee($product->name);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Add to cart');

        $this->getJson(route('products.search', ['q' => 'Fajr']))
            ->assertOk()
            ->assertJsonFragment(['name' => $product->name]);
    }

    public function test_contact_and_newsletter_forms_persist_submissions(): void
    {
        $this->from(route('contact'))->post(route('contact.store'), [
            'name' => 'Amina Noor',
            'email' => 'amina@example.com',
            'phone' => '+971500000001',
            'subject' => 'Wholesale enquiry',
            'message' => 'Please share wholesale carton pricing.',
        ])->assertRedirect(route('contact'));

        $this->from(route('home'))->post(route('newsletter.store'), [
            'email' => 'AMINA@example.com',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas(ContactMessage::class, ['email' => 'amina@example.com']);
        $this->assertDatabaseHas(NewsletterSubscriber::class, ['email' => 'amina@example.com']);
    }
}
