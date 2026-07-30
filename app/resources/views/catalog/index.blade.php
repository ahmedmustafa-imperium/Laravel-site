@extends('layouts.storefront')
@section('title', $activeCategory?->name ?? (request('q') ? 'Search results' : 'Shop all products'))

@section('content')
<div class="page-hero page-hero--compact"><div class="container"><nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>›</span><span>{{ $activeCategory?->name ?? 'Products' }}</span></nav><h1>{{ $activeCategory?->name ?? (request('q') ? 'Search results for “'.request('q').'”' : 'Shop all products') }}</h1>@if($activeCategory?->description)<p>{{ $activeCategory->description }}</p>@endif</div></div>

<section class="catalog-layout container">
    <button class="filter-mobile-trigger button button--secondary" type="button" data-filter-open>☷ Filters & sort</button>
    <aside class="catalog-sidebar" data-filter-panel>
        <div class="catalog-sidebar__head"><h2>Filter products</h2><button type="button" data-filter-close>×</button></div>
        <form method="get" action="{{ $activeCategory ? route('categories.show', $activeCategory) : route('products.index') }}">
            @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            <fieldset><legend>Categories</legend><a class="filter-link {{ !$activeCategory ? 'active' : '' }}" href="{{ route('products.index') }}">All products</a>
                @foreach($categories as $category)<details @if($activeCategory && ($activeCategory->id === $category->id || $activeCategory->parent_id === $category->id)) open @endif><summary><a href="{{ route('categories.show', $category) }}">{{ $category->name }}</a><span>+</span></summary>@foreach($category->children as $child)<a class="filter-link {{ $activeCategory?->id === $child->id ? 'active' : '' }}" href="{{ route('categories.show', $child) }}">{{ $child->name }}</a>@endforeach</details>@endforeach
            </fieldset>
            <fieldset><legend>Availability</legend><label><input type="radio" name="availability" value="" @checked(!request('availability'))> All</label><label><input type="radio" name="availability" value="in-stock" @checked(request('availability') === 'in-stock')> In stock</label><label><input type="radio" name="availability" value="out-of-stock" @checked(request('availability') === 'out-of-stock')> Out of stock</label></fieldset>
            <fieldset><legend>Price range (AED)</legend><div class="price-filter"><input type="number" min="0" step="1" name="min_price" value="{{ request('min_price') }}" placeholder="Min"><span>—</span><input type="number" min="0" step="1" name="max_price" value="{{ request('max_price') }}" placeholder="Max"></div></fieldset>
            <input type="hidden" name="sort" value="{{ request('sort', 'featured') }}"><button class="button button--full" type="submit">Apply filters</button><a class="clear-filters" href="{{ $activeCategory ? route('categories.show', $activeCategory) : route('products.index') }}">Clear all</a>
        </form>
    </aside>

    <div class="catalog-results">
        <div class="catalog-toolbar"><p><strong>{{ $products->total() }}</strong> products</p><div class="view-toggles"><button type="button" class="active" data-grid-view="grid" aria-label="Grid view">▦</button><button type="button" data-grid-view="list" aria-label="List view">☷</button></div><form method="get"><input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="availability" value="{{ request('availability') }}"><input type="hidden" name="min_price" value="{{ request('min_price') }}"><input type="hidden" name="max_price" value="{{ request('max_price') }}"><label>Show <select name="per_page" onchange="this.form.submit()">@foreach([10,15,20,25,30,50] as $count)<option value="{{ $count }}" @selected((int)request('per_page',20)===$count)>{{ $count }}</option>@endforeach</select></label><label>Sort by <select name="sort" onchange="this.form.submit()"><option value="featured" @selected(request('sort','featured')==='featured')>Featured</option><option value="newest" @selected(request('sort')==='newest')>Date, new to old</option><option value="oldest" @selected(request('sort')==='oldest')>Date, old to new</option><option value="price-asc" @selected(request('sort')==='price-asc')>Price, low to high</option><option value="price-desc" @selected(request('sort')==='price-desc')>Price, high to low</option><option value="name-asc" @selected(request('sort')==='name-asc')>A–Z</option><option value="name-desc" @selected(request('sort')==='name-desc')>Z–A</option></select></label></form></div>
        @if(request()->hasAny(['availability','min_price','max_price','q']))<div class="active-filters">@if(request('q'))<span>Search: {{ request('q') }}</span>@endif @if(request('availability'))<span>{{ str(request('availability'))->headline() }}</span>@endif @if(request('min_price') || request('max_price'))<span>AED {{ request('min_price', 0) }}–{{ request('max_price', '∞') }}</span>@endif</div>@endif
        <div class="product-grid" data-product-grid>@forelse($products as $product)<x-product-card :product="$product" />@empty<div class="empty-state empty-state--large"><span>⌕</span><h2>No products found</h2><p>Try clearing a filter or searching for something broader.</p><a class="button" href="{{ route('products.index') }}">Browse all products</a></div>@endforelse</div>
        <div class="pagination-wrap">{{ $products->links() }}</div>
    </div>
</section>
@endsection
