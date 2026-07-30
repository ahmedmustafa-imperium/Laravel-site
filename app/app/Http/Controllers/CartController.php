<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartManager $cart) {}

    public function index(): View
    {
        $summary = $this->cart->summary();
        $bestSellers = Product::active()->inStock()->where('is_featured', true)->limit(8)->get();

        return view('cart.index', compact('summary', 'bestSellers'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:100'], 'variant' => ['nullable', 'string', 'max:100']]);
        $this->cart->add($product, $data['quantity'] ?? 1, $data['variant'] ?? null);

        return back()->with('success', "{$product->name} was added to your cart.");
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:100']]);
        $this->cart->update($key, $data['quantity']);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return back()->with('success', 'Item removed from your cart.');
    }

    public function coupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['coupon' => ['required', 'string', 'max:50']]);
        $coupon = $this->cart->applyCoupon($data['coupon']);

        return back()->with('success', "Coupon {$coupon->code} applied.");
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }
}
