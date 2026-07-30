<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', ['categories' => Category::with('parent')->withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category, 'parents' => Category::whereNull('parent_id')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->data($request));

        return redirect()->route('admin.categories.edit', $category)->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => Category::whereNull('parent_id')->whereKeyNot($category->id)->orderBy('name')->get()]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->data($request, $category));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->update(['is_active' => false]);

        return redirect()->route('admin.categories.index')->with('success', 'Category archived.');
    }

    private function data(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

        $data = $request->validate([
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
                function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                    if ($value && $category?->children()->exists()) {
                        $fail('A category with child categories cannot be moved beneath another category.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', Rule::unique('categories')->ignore($category?->id)],
            'description' => ['nullable', 'string'], 'image' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'], 'is_featured' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
