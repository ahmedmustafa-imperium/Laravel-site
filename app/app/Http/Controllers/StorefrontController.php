<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(): View
    {
        $featuredCategories = Category::visible()->where('is_featured', true)
            ->orderBy('sort_order')->limit(8)->get();
        $featuredProducts = Product::active()->with('category')->where('is_featured', true)
            ->orderBy('sort_order')->limit(10)->get();

        $rails = [
            'packaging' => $this->productsFor(['bagasse-products', 'aluminium-products', 'eco-friendly', 'plastic-products']),
            'grocery' => $this->productsFor(['food-products', 'tea-coffee']),
            'tissue' => $this->productsFor(['tissue-products']),
            'pets' => $this->productsFor(['pet-products']),
        ];

        return view('home', compact('featuredCategories', 'featuredProducts', 'rails'));
    }

    private function productsFor(array $slugs)
    {
        return Product::active()->with('category')
            ->whereHas('category', fn ($query) => $query->whereIn('slug', $slugs)->orWhereHas('parent', fn ($parent) => $parent->whereIn('slug', $slugs)))
            ->orderByDesc('is_featured')->orderBy('sort_order')->limit(8)->get();
    }
}
