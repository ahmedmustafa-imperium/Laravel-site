<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.storefront', 'layouts.admin'], function ($view): void {
            $categories = collect();

            try {
                if (Schema::hasTable('categories')) {
                    $categories = Category::active()
                        ->whereNull('parent_id')
                        ->with(['children' => fn ($query) => $query->active()])
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();
                }
            } catch (\Throwable) {
                // Keep the installer and first migration screen renderable.
            }

            $view->with([
                'navCategories' => $categories,
                'cartCount' => collect(session('cart', []))->sum('quantity'),
            ]);
        });
    }
}
