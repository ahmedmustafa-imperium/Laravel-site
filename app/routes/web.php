<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/shop', [CatalogController::class, 'index'])->name('products.index');
Route::get('/collections/{category}', [CatalogController::class, 'category'])->name('categories.show');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');
Route::get('/search/suggestions', [CatalogController::class, 'search'])->name('products.search');

Route::get('/about-us', [PageController::class, 'about'])->name('about');
Route::get('/contact-us', [PageController::class, 'contact'])->name('contact');
Route::post('/contact-us', [PageController::class, 'contactStore'])->middleware('throttle:10,1')->name('contact.store');
Route::post('/newsletter', [PageController::class, 'newsletter'])->middleware('throttle:5,1')->name('newsletter.store');

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/products/{product}', [CartController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::patch('/items/{key}', [CartController::class, 'update'])->name('update');
    Route::delete('/items/{key}', [CartController::class, 'destroy'])->name('destroy');
    Route::post('/coupon', [CartController::class, 'coupon'])->name('coupon');
    Route::delete('/coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
});

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:5,1')->name('checkout.store');
Route::get('/checkout/payment/{provider}/{order}/success', [CheckoutController::class, 'gatewaySuccess'])->name('checkout.gateway.success');
Route::get('/checkout/payment/{provider}/{order}/cancel', [CheckoutController::class, 'gatewayCancel'])->name('checkout.gateway.cancel');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->middleware('signed')->name('checkout.success');
Route::post('/payments/webhooks/stripe', [CheckoutController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/payments/webhooks/paypal', [CheckoutController::class, 'paypalWebhook'])->name('webhooks.paypal');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticationController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthenticationController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthenticationController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthenticationController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [AuthenticationController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthenticationController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthenticationController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthenticationController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticationController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/account/orders/{order}', [AccountController::class, 'order'])->name('account.orders.show');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::resource('categories', AdminCategoryController::class)->except('show');
    Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
    Route::resource('coupons', AdminCouponController::class)->except('show');
});
