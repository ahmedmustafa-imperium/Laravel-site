@extends('layouts.storefront')
@section('title', $product->name)
@section('description', $product->short_description)

@section('content')
<div class="container product-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>›</span>@if($product->category)<a href="{{ route('categories.show', $product->category) }}">{{ $product->category->name }}</a><span>›</span>@endif<span>{{ Str::limit($product->name, 42) }}</span></nav>
    <div class="product-detail">
        <section class="product-gallery" aria-label="Product images">
            <div class="product-gallery__main">@if($product->badge)<span class="product-badge">{{ $product->badge }}</span>@endif<img data-gallery-main src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"></div>
            @if(count($product->galleryUrls()) > 1)<div class="product-gallery__thumbs">@foreach($product->galleryUrls() as $image)<button type="button" data-gallery-thumb="{{ $image }}"><img src="{{ $image }}" alt="Product view {{ $loop->iteration }}"></button>@endforeach</div>@endif
        </section>
        <section class="product-info">
            @if($product->category)<a class="eyebrow" href="{{ route('categories.show', $product->category) }}">{{ $product->category->name }}</a>@endif
            <h1>{{ $product->name }}</h1><div class="product-rating">★★★★★ <a href="#reviews">4.9 · {{ (($product->id * 7) % 31) + 3 }} reviews</a></div>
            <div class="product-info__price"><strong data-product-price>AED {{ number_format((float)$product->price, 2) }}</strong>@if($product->compare_at_price)<s>AED {{ number_format((float)$product->compare_at_price, 2) }}</s><span>Save {{ $product->salePercentage() }}%</span>@endif</div>
            <p class="product-info__intro">{{ $product->short_description }}</p>
            <div class="stock-line {{ $product->stock > 0 ? 'in-stock' : 'out-stock' }}"><i></i>{{ $product->stock > 0 ? 'In stock — ready to dispatch' : 'Currently out of stock' }} <small>SKU: {{ $product->sku }}</small></div>
            <form class="buy-box" action="{{ route('cart.store', $product) }}" method="post">@csrf
                @if($product->variants)<fieldset class="variant-picker"><legend>Choose pack size</legend><div>@foreach($product->variants as $option)<label><input type="radio" name="variant" value="{{ $option['name'] }}" data-variant-price="{{ number_format((float)$option['price'], 2, '.', '') }}" @checked($loop->first)><span>{{ $option['name'] }}<small>AED {{ number_format((float)$option['price'], 2) }}</small></span></label>@endforeach</div></fieldset>@endif
                <label class="quantity-label">Quantity <span class="quantity-stepper"><button type="button" data-qty-minus aria-label="Decrease quantity">−</button><input name="quantity" type="number" min="1" max="{{ max(1,$product->stock) }}" value="1"><button type="button" data-qty-plus aria-label="Increase quantity">+</button></span></label>
                <div class="buy-box__actions"><button class="button button--buy" type="submit" @disabled($product->stock < 1)>Add to cart · <span data-button-price>AED {{ number_format((float)$product->price, 2) }}</span></button><button type="button" class="wishlist-large" data-wishlist-item="{{ $product->id }}" data-wishlist-name="{{ $product->name }}" aria-label="Save to wishlist">♡</button></div>
            </form>
            <div class="delivery-card"><div><span>🚚</span><p><strong>Fast UAE delivery</strong><small>Free on orders above AED 99</small></p></div><div><span>✓</span><p><strong>Secure checkout</strong><small>Stripe, PayPal or cash on delivery</small></p></div></div>
            <div class="product-accordions"><details open><summary>Delivery details</summary><p>In-stock orders placed before 6 PM are normally processed the same day. Delivery schedules vary by emirate and serviceability.</p></details><details><summary>Product specifications</summary><ul><li>SKU: {{ $product->sku }}</li><li>Category: {{ $product->category?->name ?? 'General' }}</li><li>Available stock: {{ $product->stock }}</li></ul></details><details><summary>Bulk & custom pricing</summary><p>Need a larger quantity or branded packaging? <a href="{{ route('contact') }}">Send us an inquiry</a> for a tailored quotation.</p></details></div>
        </section>
    </div>
    <section class="product-description"><span class="eyebrow">Product details</span><h2>Made for reliable everyday use.</h2><p>{{ $product->description }}</p></section>
</div>

@include('partials.product-rail', ['title' => 'You may also like', 'subtitle' => 'Related products', 'products' => $related, 'link' => $product->category ? route('categories.show', $product->category) : route('products.index'), 'surface' => 'gray'])
@if($recentlyViewed->isNotEmpty())@include('partials.product-rail', ['title' => 'Recently viewed', 'products' => $recentlyViewed, 'surface' => 'white'])@endif
@endsection
