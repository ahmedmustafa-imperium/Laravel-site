<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active && (! $category->parent_id || $category->parent()->active()->exists()), 404);

        return $this->listing($request, $category);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);
        $product->load('category');
        $related = Product::active()->whereKeyNot($product->id)
            ->where('category_id', $product->category_id)->limit(8)->get();

        $recent = collect(session('recently_viewed', []))->reject(fn ($slug) => $slug === $product->slug)->take(6);
        $recentlyViewed = Product::active()->whereIn('slug', $recent)->get()->sortBy(fn ($item) => $recent->search($item->slug));
        session()->put('recently_viewed', collect([$product->slug])->merge($recent)->unique()->take(8)->values()->all());

        return view('catalog.show', compact('product', 'related', 'recentlyViewed'));
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Product::active()->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
        })->limit(6)->get()->map(fn (Product $product) => [
            'name' => $product->name,
            'price' => number_format((float) $product->price, 2),
            'image' => $product->imageUrl(),
            'url' => route('products.show', $product),
        ]));
    }

    private function listing(Request $request, ?Category $activeCategory = null): View
    {
        $query = Product::active()->with('category');

        if ($activeCategory) {
            $categoryIds = $activeCategory->children()->active()->pluck('id')->prepend($activeCategory->id);
            $query->whereIn('category_id', $categoryIds);
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', max(0, (float) $request->min_price));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', max(0, (float) $request->max_price));
        }
        if ($request->availability === 'in-stock') {
            $query->where('stock', '>', 0);
        }
        if ($request->availability === 'out-of-stock') {
            $query->where('stock', 0);
        }

        match ($request->query('sort', 'featured')) {
            'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'),
            'name-asc' => $query->orderBy('name'),
            'name-desc' => $query->orderByDesc('name'),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->latest(),
        };

        $perPage = in_array((int) $request->query('per_page'), [10, 15, 20, 25, 30, 50], true) ? (int) $request->per_page : 20;
        $products = $query->paginate($perPage)->withQueryString();
        $categories = Category::active()->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('sort_order')->get();

        return view('catalog.index', compact('products', 'categories', 'activeCategory'));
    }
}
