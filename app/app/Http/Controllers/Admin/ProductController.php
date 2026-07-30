<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $products = Product::with('category')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product, 'categories' => Category::active()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->normalise($request, $data);
        $product = Product::create($data);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::active()->orderBy('name')->get(),
            'movements' => $product->inventoryMovements()->with(['order', 'user'])->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->normalise($request, $this->validated($request, $product));
        $newStock = (int) $data['stock'];
        unset($data['stock']);
        $product->update($data);
        $this->inventory->adjust($product, $newStock, $request->user(), 'Product edited in admin');

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return redirect()->route('admin.products.index')->with('success', 'Product archived.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'], 'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($product?->id)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product?->id)],
            'short_description' => ['nullable', 'string', 'max:500'], 'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'], 'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'], 'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:1000'], 'image_file' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'string'], 'variants_json' => ['nullable', 'json'], 'badge' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'], 'is_featured' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalise(Request $request, array $data): array
    {
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['gallery'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['gallery'] ?? '')))
            ->map(fn (string $image) => trim($image))
            ->filter()
            ->values()
            ->all();
        $data['variants'] = $this->normaliseVariants($data['variants_json'] ?? null);
        unset($data['variants_json'], $data['image_file']);

        if ($request->hasFile('image_file')) {
            $data['image'] = 'storage/'.$request->file('image_file')->store('products', 'public');
        }

        return $data;
    }

    private function normaliseVariants(?string $json): ?array
    {
        if (! filled($json)) {
            return null;
        }

        $variants = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($variants) || ! array_is_list($variants) || $variants === []) {
            throw ValidationException::withMessages([
                'variants_json' => 'Variants must be a non-empty JSON list of name and price objects.',
            ]);
        }

        $names = [];

        return collect($variants)->map(function ($variant, int $index) use (&$names): array {
            $name = is_array($variant) ? trim((string) ($variant['name'] ?? '')) : '';
            $price = is_array($variant) ? ($variant['price'] ?? null) : null;
            $key = mb_strtolower($name);

            if ($name === '' || mb_strlen($name) > 100 || ! is_numeric($price) || (float) $price < 0) {
                throw ValidationException::withMessages([
                    'variants_json' => 'Variant '.($index + 1).' needs a name (maximum 100 characters) and a non-negative numeric price.',
                ]);
            }

            if (in_array($key, $names, true)) {
                throw ValidationException::withMessages([
                    'variants_json' => "Variant names must be unique; '{$name}' appears more than once.",
                ]);
            }

            $names[] = $key;

            return ['name' => $name, 'price' => round((float) $price, 2)];
        })->all();
    }
}
