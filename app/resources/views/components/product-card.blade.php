@props(['product'])
<article class="product-card" data-product-card="{{ $product->id }}">
    <div class="product-card__media">
        @if($product->badge)<span class="product-badge">{{ $product->badge }}</span>@elseif($product->salePercentage())<span class="product-badge">SAVE {{ $product->salePercentage() }}%</span>@endif
        <button class="product-wishlist" type="button" data-wishlist-item="{{ $product->id }}" data-wishlist-name="{{ $product->name }}" aria-label="Save {{ $product->name }} to wishlist">♡</button>
        <a href="{{ route('products.show', $product) }}"><img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy"></a>
        <a class="quick-view" href="{{ route('products.show', $product) }}">Quick view</a>
    </div>
    <div class="product-card__body">
        @if($product->category)<a class="product-card__category" href="{{ route('categories.show', $product->category) }}">{{ $product->category->name }}</a>@endif
        <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="product-rating" aria-label="5 out of 5 stars">★★★★★ <small>({{ (($product->id * 7) % 31) + 3 }})</small></div>
        <div class="product-price"><strong>AED {{ number_format((float)$product->price, 2) }}</strong>@if($product->compare_at_price)<s>AED {{ number_format((float)$product->compare_at_price, 2) }}</s>@endif</div>
        <form action="{{ route('cart.store', $product) }}" method="post">@csrf<input type="hidden" name="quantity" value="1">@if($product->variants)<input type="hidden" name="variant" value="{{ $product->variants[0]['name'] ?? '' }}">@endif<button class="button button--card" type="submit" @disabled($product->stock < 1)>{{ $product->stock > 0 ? 'Add to cart' : 'Out of stock' }}</button></form>
    </div>
</article>
