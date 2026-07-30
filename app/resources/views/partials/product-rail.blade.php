@props(['title', 'subtitle' => null, 'products', 'link' => null, 'surface' => 'white'])
<section class="product-section product-section--{{ $surface }}">
    <div class="container">
        <div class="section-heading"><div>@if($subtitle)<span class="eyebrow">{{ $subtitle }}</span>@endif<h2>{{ $title }}</h2></div>@if($link)<a href="{{ $link }}">View all <span>→</span></a>@endif</div>
        <div class="product-rail">@forelse($products as $product)<x-product-card :product="$product" />@empty<p class="empty-state">Products will appear here after the catalog is seeded.</p>@endforelse</div>
    </div>
</section>
