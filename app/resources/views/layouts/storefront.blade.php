<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#000066">
    <title>@yield('title', 'SNH Packing') · SNH Packing</title>
    <meta name="description" content="@yield('description', 'Food packaging, hygiene, grocery and pet supplies delivered across the UAE.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/site.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <div class="announcement" aria-label="Store announcements">
        <div class="announcement__track">
            <span>Enjoy <strong>10% OFF</strong> on your first order — Code: <strong>WELCOME10</strong></span>
            <span>Enjoy <strong>FREE DELIVERY</strong> on orders above AED 99</span>
            <span aria-hidden="true">Enjoy <strong>10% OFF</strong> on your first order — Code: <strong>WELCOME10</strong></span>
            <span aria-hidden="true">Enjoy <strong>FREE DELIVERY</strong> on orders above AED 99</span>
        </div>
    </div>

    <header class="site-header">
        <div class="header-utility container">
            <span>Mon–Sat: 9:00 AM–6:00 PM</span>
            <a href="tel:+971523993759">Need help? +971 52 399 3759</a>
            <span class="header-utility__spacer"></span>
            <a href="{{ route('about') }}">About Us</a>
            <a href="{{ route('contact') }}">Contact</a>
            <span>EN / AR</span>
        </div>

        <div class="header-main container">
            <button class="icon-button mobile-only" type="button" data-menu-toggle aria-label="Open menu" aria-controls="mobile-menu">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a class="brand" href="{{ route('home') }}" aria-label="SNH Packing home">
                <img src="{{ asset('images/snh/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('images/snh/logo-fallback.svg') }}'" alt="SNH Packing">
            </a>
            <div class="header-search" data-search-wrapper>
                <form action="{{ route('products.index') }}" method="get" role="search">
                    <label class="sr-only" for="site-search">Search products</label>
                    <input id="site-search" name="q" type="search" value="{{ request('q') }}" placeholder="What are you looking for?" autocomplete="off" data-product-search>
                    <button type="submit" aria-label="Search">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
                    </button>
                </form>
                <div class="search-suggestions" data-search-results hidden></div>
            </div>
            <nav class="header-actions" aria-label="Account actions">
                <button class="header-action wishlist-trigger" type="button" data-wishlist-open>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20.8 4.6a5.4 5.4 0 0 0-7.6 0L12 5.8l-1.2-1.2a5.4 5.4 0 1 0-7.6 7.6L12 21l8.8-8.8a5.4 5.4 0 0 0 0-7.6Z"/></svg>
                    <span class="desktop-only">Wishlist</span><b data-wishlist-count>0</b>
                </button>
                @auth
                    <a class="header-action" href="{{ route('account.index') }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <span class="desktop-only">{{ Str::before(auth()->user()->name, ' ') }}</span>
                    </a>
                @else
                    <a class="header-action" href="{{ route('login') }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <span class="desktop-only">Sign In</span>
                    </a>
                @endauth
                <a class="header-action header-cart" href="{{ route('cart.index') }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 4h2l2.5 11h10l2-7H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                    <span class="desktop-only">Cart</span><b>{{ $cartCount }}</b>
                </a>
            </nav>
        </div>

        <nav class="primary-nav" aria-label="Main navigation">
            <div class="container primary-nav__inner">
                <div class="categories-nav">
                    <button type="button" class="categories-nav__trigger"><span class="grid-icon">⌘</span> Categories <span>⌄</span></button>
                    <div class="mega-menu">
                        <div class="mega-menu__grid">
                            @forelse($navCategories as $category)
                                <section>
                                    <a href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>
                                    @foreach($category->children->take(7) as $child)
                                        <a href="{{ route('categories.show', $child) }}">{{ $child->name }}</a>
                                    @endforeach
                                </section>
                            @empty
                                <p>Run the database seeder to load categories.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                <a href="{{ route('products.index', ['sort' => 'featured']) }}">Mega Deals</a>
                <a href="{{ route('categories.show', 'tissue-products') }}">Tissue Products</a>
                <a href="{{ route('products.index', ['sort' => 'featured']) }}">Food Products</a>
                <a href="{{ route('categories.show', 'pet-products') }}">Pet Brands</a>
                <a href="{{ route('categories.show', 'eco-friendly') }}">Eco Friendly</a>
                <a href="{{ route('contact') }}">Customization</a>
                <a href="{{ route('about') }}">Info & Help</a>
                <span class="primary-nav__spacer"></span>
                <a href="{{ route('contact') }}">Locate Us</a>
            </div>
        </nav>
    </header>

    <aside id="mobile-menu" class="mobile-drawer" data-mobile-menu aria-hidden="true">
        <div class="mobile-drawer__head"><strong>Browse SNH</strong><button type="button" data-menu-close aria-label="Close menu">×</button></div>
        <nav>
            <a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Shop All</a>
            @foreach($navCategories as $category)
                <details><summary>{{ $category->name }}</summary>
                    <a href="{{ route('categories.show', $category) }}">View all</a>
                    @foreach($category->children as $child)<a href="{{ route('categories.show', $child) }}">{{ $child->name }}</a>@endforeach
                </details>
            @endforeach
            <a href="{{ route('about') }}">About Us</a><a href="{{ route('contact') }}">Contact Us</a>
        </nav>
    </aside>
    <div class="drawer-backdrop" data-drawer-backdrop hidden></div>

    @include('partials.flash')

    <main id="main-content">
        @yield('content')
    </main>

    @include('partials.newsletter')

    <footer class="site-footer">
        <div class="container footer-grid">
            <section class="footer-brand">
                <img src="{{ asset('images/snh/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('images/snh/logo-fallback.svg') }}'" alt="SNH Packing">
                <p>Your trusted UAE supplier for food packaging, hygiene products, grocery essentials and pet care.</p>
                <a href="tel:+971523993759">+971 52 399 3759</a>
                <a href="mailto:contact@snhuae.com">contact@snhuae.com</a>
            </section>
            <section><h2>Shop</h2><a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Mega Deals</a><a href="{{ route('products.index', ['sort' => 'featured']) }}">Food Products</a><a href="{{ route('categories.show', 'pet-products') }}">Pet Products</a><a href="{{ route('contact') }}">Customization</a></section>
            <section><h2>Popular Categories</h2>@foreach($navCategories->take(7) as $category)<a href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>@endforeach</section>
            <section><h2>Further Info</h2><a href="{{ route('about') }}">About Us</a><a href="{{ route('contact') }}">Contact Us</a><a href="{{ route('account.index') }}">My Account</a><a href="#">Privacy Policy</a><a href="#">Terms & Conditions</a><a href="#">Returns Policy</a></section>
            <section><h2>Our Locations</h2><p><strong>Dubai</strong><br>Warehouses 17–20, 22nd St, Al Quoz Industrial Area 3</p><p><strong>Umm Al Quwain</strong><br>Sector 4, Plot 30, Block 5, New Industrial Area</p></section>
        </div>
        <div class="container footer-bottom"><span>© {{ date('Y') }} SNH Packing. All rights reserved.</span><span class="payment-marks">VISA · Mastercard · Stripe · PayPal</span></div>
    </footer>

    <a class="whatsapp-float" href="https://wa.me/971523993759" target="_blank" rel="noopener" aria-label="Ask about bulk pricing on WhatsApp"><span>●</span><b>Bulk pricing</b></a>

    <nav class="mobile-toolbar" aria-label="Mobile shortcuts">
        <a href="{{ route('home') }}"><span>⌂</span>Home</a><button type="button" data-mobile-search><span>⌕</span>Search</button>
        <a href="{{ route('products.index') }}"><span>▦</span>Shop</a><a href="{{ auth()->check() ? route('account.index') : route('login') }}"><span>♙</span>Account</a>
        <a href="{{ route('cart.index') }}"><span>🛒</span>Cart<em>{{ $cartCount }}</em></a>
    </nav>

    <dialog class="welcome-modal" data-welcome-modal>
        <button type="button" class="welcome-modal__close" data-modal-close aria-label="Close">×</button>
        <span class="eyebrow">A little welcome gift</span><h2>Save 10% on your first order</h2>
        <p>Use the code below at cart. Free UAE delivery is available above AED 99.</p>
        <button type="button" class="coupon-copy" data-copy-coupon><span>WELCOME10</span><b>Copy code</b></button>
        <a class="button" href="{{ route('products.index') }}">Start shopping</a>
    </dialog>
    <div class="toast" data-toast role="status" aria-live="polite"></div>
    @stack('scripts')
</body>
</html>
