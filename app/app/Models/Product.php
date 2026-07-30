<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'category_id', 'name', 'slug', 'sku', 'short_description', 'description',
    'price', 'compare_at_price', 'stock', 'low_stock_threshold', 'image', 'gallery',
    'variants', 'badge', 'is_featured', 'is_active', 'sort_order',
])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'gallery' => 'array',
            'variants' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->where('products.is_active', true)
            ->where(function ($visible): void {
                $visible->whereNull('products.category_id')
                    ->orWhereHas('category', function ($category): void {
                        $category->where('is_active', true)
                            ->where(function ($lineage): void {
                                $lineage->whereNull('parent_id')
                                    ->orWhereHas('parent', fn ($parent) => $parent->where('is_active', true));
                            });
                    });
            });
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function imageUrl(): string
    {
        return self::resolveImageUrl($this->image, 'images/snh/product-placeholder.svg');
    }

    public static function resolveImageUrl(?string $path, string $fallback): string
    {
        if (! $path) {
            return asset($fallback);
        }
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset($path);
    }

    public function galleryUrls(): array
    {
        $images = array_values(array_filter(array_merge([$this->image], $this->gallery ?? [])));

        return array_map(fn (string $image) => self::resolveImageUrl($image, 'images/snh/product-placeholder.svg'), array_unique($images));
    }

    public function priceForVariant(?string $variant): float
    {
        if (! $variant || ! $this->variants) {
            return (float) $this->price;
        }
        $match = collect($this->variants)->first(fn (array $option) => ($option['name'] ?? null) === $variant);

        return (float) ($match['price'] ?? $this->price);
    }

    public function salePercentage(): ?int
    {
        $compareAt = (float) $this->compare_at_price;
        $price = (float) $this->price;

        return $compareAt > $price && $compareAt > 0 ? (int) round((($compareAt - $price) / $compareAt) * 100) : null;
    }
}
