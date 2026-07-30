@extends('layouts.storefront')

@section('title', 'UAE Food Packaging, Hygiene & Grocery Supplier')
@section('description', 'Shop packaging, hygiene, tissue, grocery and pet essentials with fast delivery across the UAE.')

@section('content')
<section class="hero container">
    <picture>
        <img src="{{ asset('images/snh/hero.jpg') }}" alt="SNH Packing — everything you need, packed with care" fetchpriority="high">
    </picture>
    <div class="hero__fallback"><span class="eyebrow">UAE-wide delivery</span><h1>Everything you need.<br><em>Packed with care.</em></h1><p>Packaging, hygiene, grocery and pet essentials for homes and businesses.</p><a class="button button--lime" href="{{ route('products.index') }}">Shop the collection</a></div>
</section>

<section class="category-section container">
    <div class="section-heading section-heading--center"><div><span class="eyebrow">Find it fast</span><h2>Shop by categories</h2></div></div>
    <div class="category-rail">
        @forelse($featuredCategories as $category)
            <a class="category-tile" href="{{ route('categories.show', $category) }}"><span><img src="{{ $category->imageUrl() }}" alt="" loading="lazy"></span><strong>{{ $category->name }}</strong></a>
        @empty
            @foreach(['Aluminium', 'Eco-Friendly', 'Facial Tissues', 'Maxi Rolls', 'Grocery', 'Tea & Coffee', 'Small Pets', 'Dog Food'] as $category)
                <div class="category-tile"><span><img src="{{ asset('images/snh/category-placeholder.svg') }}" alt=""></span><strong>{{ $category }}</strong></div>
            @endforeach
        @endforelse
    </div>
</section>

<section class="promo-section container">
    <a class="promo promo--wide" href="{{ route('products.index', ['sort' => 'featured']) }}"><img src="{{ asset('images/snh/promo-mega.webp') }}" alt="Mega deals on SNH essentials" loading="lazy"></a>
    <div class="promo-grid">
        <a class="promo" href="{{ route('categories.show', 'paper-cups') }}"><img src="{{ asset('images/snh/promo-packaging.webp') }}" alt="Paper and plastic packaging" loading="lazy"></a>
        <a class="promo" href="{{ route('categories.show', 'tissue-products') }}"><img src="{{ asset('images/snh/promo-tissue.webp') }}" alt="Soft and hygienic tissue products" loading="lazy"></a>
        <a class="promo" href="{{ route('categories.show', 'food-products') }}"><img src="{{ asset('images/snh/promo-food.webp') }}" alt="ATF food and grocery products" loading="lazy"></a>
        <a class="promo" href="{{ route('categories.show', 'pet-products') }}"><img src="{{ asset('images/snh/promo-pets.webp') }}" alt="Pet food and supplies" loading="lazy"></a>
    </div>
</section>

<section class="clients-section">
    <div class="container"><div class="section-heading section-heading--center"><div><span class="eyebrow">Chosen every day</span><h2>Our clients</h2></div></div>
        <div class="client-rail">@foreach(['PROTEIN HOUSE', 'SALT', 'PITFIRE', 'G.O.A.T.', 'BONBIRD', 'PICKL', 'COLD STONE', 'MAMA’ESH', 'MAJID AL FUTTAIM', 'ALLÔ BEIRUT', 'noon'] as $client)<span>{{ $client }}</span>@endforeach</div>
    </div>
</section>

@include('partials.product-rail', ['title' => 'Packaging & home essentials', 'subtitle' => 'Built for busy days', 'products' => $rails['packaging']->merge($featuredProducts)->unique('id')->take(10), 'link' => route('categories.show', 'bagasse-products'), 'surface' => 'gray'])

<section class="split-promos container">
    <a href="{{ route('categories.show', 'charcoal-wood') }}"><span>Fire up the flavour</span><h2>Charcoal & wood</h2><b>Shop now →</b></a>
    <a href="{{ route('categories.show', 'aurasync') }}"><span>Smarter everyday living</span><h2>AuraSync</h2><b>Discover →</b></a>
</section>

@include('partials.product-rail', ['title' => 'Food & grocery favourites', 'subtitle' => 'Pantry picks', 'products' => $rails['grocery'], 'link' => route('categories.show', 'food-products'), 'surface' => 'white'])
@include('partials.product-rail', ['title' => 'Soft, fresh & hygienic', 'subtitle' => 'Everyday tissue care', 'products' => $rails['tissue'], 'link' => route('categories.show', 'tissue-products'), 'surface' => 'green'])
@include('partials.product-rail', ['title' => 'New for your best friend', 'subtitle' => 'Pet favourites', 'products' => $rails['pets'], 'link' => route('categories.show', 'pet-products'), 'surface' => 'white'])

<section class="story-section">
    <div class="container story-grid">
        <div class="story-visual"><span class="story-visual__number">2,500+</span><span>carefully sourced products</span><div class="story-visual__stamp">UAE<br>TRUSTED</div></div>
        <div class="story-copy"><span class="eyebrow">One dependable partner</span><h2>Packaging and essentials that keep your business moving.</h2><p>SNH Packing supplies restaurants, cafés, caterers, offices and households throughout the UAE. From sustainable takeaway packaging and custom-printed cups to tissue, cleaning, grocery and pet care, our range is built around the way customers actually work and live.</p><p>We combine reliable stock, practical pack sizes and responsive support, with wholesale pricing available for growing businesses.</p><details><summary>Read more about our range</summary><div><h3>Food packaging without the guesswork</h3><p>Choose from aluminium, bagasse, paper, plastic and wooden formats for hot, cold, wet and dry food service.</p><h3>Better options for a lower-impact future</h3><p>Our eco-friendly selection helps businesses move toward recyclable, compostable and responsibly sourced materials.</p></div></details><a class="button" href="{{ route('about') }}">Our story</a></div>
    </div>
</section>

<section class="faq-section container">
    <div class="section-heading section-heading--center"><div><span class="eyebrow">Good to know</span><h2>Frequently asked questions</h2></div></div>
    <div class="faq-grid">
        <details open><summary>Do you deliver throughout the UAE?</summary><p>Yes. We serve all seven emirates. Orders over AED 99 qualify for free standard delivery.</p></details>
        <details><summary>Can I order wholesale or custom packaging?</summary><p>Absolutely. Send your quantities, artwork and timing through our contact page or WhatsApp for a tailored quote.</p></details>
        <details><summary>How quickly will my order arrive?</summary><p>In-stock orders placed before 6 PM are normally processed the same day. Delivery timing depends on the emirate and service area.</p></details>
    </div>
</section>

<section class="reviews-section">
    <div class="container"><div class="section-heading section-heading--center"><div><span class="eyebrow">Real customer notes</span><h2>What our customers are saying</h2></div></div>
        <div class="review-grid">
            @foreach([['“Quick, professional and the packaging quality was excellent.”','Rana · Dubai'],['“The team helped us choose the right containers for our new menu.”','Omar · Sharjah'],['“Great range, fair prices and everything arrived neatly packed.”','Maya · Abu Dhabi']] as [$quote,$name])
                <blockquote><div class="product-rating">★★★★★</div><p>{{ $quote }}</p><cite>{{ $name }}</cite></blockquote>
            @endforeach
        </div>
    </div>
</section>

<section class="social-section container"><div class="section-heading"><div><span class="eyebrow">Follow the packing floor</span><h2>#SNHPacking</h2></div><a href="https://www.instagram.com/" target="_blank" rel="noopener">Follow on Instagram →</a></div>
    <div class="social-grid"><img src="{{ asset('images/snh/promo-packaging.webp') }}" alt="SNH packaging collection" loading="lazy"><img src="{{ asset('images/snh/promo-food.webp') }}" alt="ATF grocery collection" loading="lazy"><img src="{{ asset('images/snh/promo-tissue.webp') }}" alt="Tissue collection" loading="lazy"><img src="{{ asset('images/snh/promo-pets.webp') }}" alt="Pet collection" loading="lazy"></div>
</section>

<section class="assurance"><div class="container assurance-grid">
    <div><span>♧</span><p><strong>Free delivery</strong><small>On orders above AED 99</small></p></div>
    <div><span>⌾</span><p><strong>Secure payment</strong><small>Stripe and PayPal ready</small></p></div>
    <div><span>◇</span><p><strong>Unique everything</strong><small>2,500+ practical products</small></p></div>
    <div><span>◎</span><p><strong>Helpful support</strong><small>Real people, quick answers</small></p></div>
</div></section>
@endsection
