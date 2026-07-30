@extends('layouts.storefront')
@section('title', 'Your cart')

@section('content')
<div class="page-hero page-hero--compact"><div class="container"><nav class="breadcrumbs"><a href="{{ route('home') }}">Home</a><span>›</span><span>Your cart</span></nav><h1>Your cart</h1></div></div>

<section class="container cart-page">
    @if($summary['items']->isEmpty())
        <div class="empty-cart"><div class="empty-cart__icon">🛒</div><span class="eyebrow">Your next favourite is waiting</span><h2>Your cart is empty</h2><p>Browse practical essentials for home and business, then come back when you’re ready.</p><a class="button" href="{{ route('products.index') }}">Continue shopping</a></div>
    @else
        @if($summary['free_shipping_remaining'] > 0)<div class="shipping-progress"><p>Add <strong>AED {{ number_format($summary['free_shipping_remaining'],2) }}</strong> more for free delivery.</p><div><i style="width:{{ min(100, (($summary['subtotal']-$summary['discount'])/99)*100) }}%"></i></div></div>@else<div class="shipping-progress shipping-progress--complete"><p>✓ You’ve unlocked <strong>free UAE delivery</strong>.</p><div><i style="width:100%"></i></div></div>@endif
        <div class="cart-layout">
            <div class="cart-lines">
                <div class="cart-lines__head"><span>Product</span><span>Price</span><span>Quantity</span><span>Total</span></div>
                @foreach($summary['items'] as $line)
                    <article class="cart-line"><a class="cart-line__image" href="{{ route('products.show', $line['product']) }}"><img src="{{ $line['product']->imageUrl() }}" alt="{{ $line['product']->name }}"></a><div class="cart-line__info"><a href="{{ route('products.show', $line['product']) }}">{{ $line['product']->name }}</a>@if($line['variant'])<small>Pack size: {{ $line['variant'] }}</small>@endif<small>SKU: {{ $line['product']->sku }}</small><form action="{{ route('cart.destroy', $line['key']) }}" method="post">@csrf @method('DELETE')<button class="text-button" type="submit">Remove</button></form></div><div class="cart-line__price">AED {{ number_format($line['price'],2) }}</div><form class="cart-line__quantity" action="{{ route('cart.update', $line['key']) }}" method="post">@csrf @method('PATCH')<span class="quantity-stepper"><button type="button" data-qty-minus>−</button><input name="quantity" type="number" min="0" max="{{ $line['product']->stock }}" value="{{ $line['quantity'] }}" onchange="this.form.submit()"><button type="button" data-qty-plus>+</button></span><noscript><button type="submit">Update</button></noscript></form><strong class="cart-line__total">AED {{ number_format($line['line_total'],2) }}</strong></article>
                @endforeach
                <a class="continue-shopping" href="{{ route('products.index') }}">← Continue shopping</a>
            </div>
            <aside class="order-summary"><h2>Order summary</h2><div><span>Subtotal</span><strong>AED {{ number_format($summary['subtotal'],2) }}</strong></div>@if($summary['coupon'])<div class="discount-line"><span>Discount ({{ $summary['coupon']->code }})</span><strong>− AED {{ number_format($summary['discount'],2) }}</strong></div>@endif<div><span>Delivery</span><strong>{{ $summary['shipping'] > 0 ? 'AED '.number_format($summary['shipping'],2) : 'FREE' }}</strong></div><div class="order-summary__total"><span>Total</span><strong>AED {{ number_format($summary['total'],2) }}</strong></div><small>VAT, if applicable, is included in displayed prices.</small>
                @if($summary['coupon'])<div class="applied-coupon"><span>✓ {{ $summary['coupon']->code }} applied</span><form action="{{ route('cart.coupon.remove') }}" method="post">@csrf @method('DELETE')<button type="submit">Remove</button></form></div>@else<form class="coupon-form" action="{{ route('cart.coupon') }}" method="post">@csrf<label for="coupon">Have a coupon?</label><div><input id="coupon" name="coupon" placeholder="Enter code"><button type="submit">Apply</button></div></form>@endif
                <a class="button button--full button--buy" href="{{ route('checkout.index') }}">Secure checkout →</a><p class="secure-note">🔒 Encrypted and secure checkout</p>
            </aside>
        </div>
    @endif
</section>
@if($summary['items']->isEmpty() || $bestSellers->isNotEmpty())@include('partials.product-rail', ['title' => 'Best sellers', 'subtitle' => 'Loved by SNH customers', 'products' => $bestSellers, 'link' => route('products.index'), 'surface' => 'gray'])@endif
@endsection
