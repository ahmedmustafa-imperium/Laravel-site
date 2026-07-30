<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;

trait CreatesCommerceData
{
    protected function createProduct(array $attributes = []): Product
    {
        $category = $attributes['category'] ?? Category::create([
            'name' => 'Tissue Products',
            'slug' => 'tissue-products',
            'description' => 'Tissue and hygiene essentials.',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        unset($attributes['category']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Al Fajr Facial Tissue 150 Sheets',
            'slug' => 'al-fajr-facial-tissue-150-sheets',
            'sku' => 'AF-150-TEST',
            'short_description' => 'Soft, hygienic two-ply facial tissue.',
            'description' => 'Reliable everyday tissue for homes and businesses.',
            'price' => 60,
            'compare_at_price' => 70,
            'stock' => 10,
            'low_stock_threshold' => 2,
            'image' => 'images/snh/product-tissue.svg',
            'gallery' => [],
            'variants' => null,
            'badge' => 'SALE',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }
}
