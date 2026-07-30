<?php

namespace App\Http\Controllers;

use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Services\CartManager;
use App\Services\OrderService;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private CartManager $cart, private OrderService $orders) {}

    public function index(): View|RedirectResponse
    {
        $summary = $this->cart->summary();
        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Add a product before checking out.']);
        }

        $checkoutToken = (string) session('checkout_token', '');
        if ($checkoutToken === '') {
            $checkoutToken = (string) Str::uuid();
            session()->put('checkout_token', $checkoutToken);
        }

        return view('checkout.index', ['summary' => $summary, 'customer' => auth()->user(), 'checkoutToken' => $checkoutToken]);
    }

    public function store(Request $request, StripeGateway $stripe, PayPalGateway $paypal): RedirectResponse
    {
        $data = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'emirate' => ['required', Rule::in(['Abu Dhabi', 'Ajman', 'Dubai', 'Fujairah', 'Ras Al Khaimah', 'Sharjah', 'Umm Al Quwain'])],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cod', 'stripe', 'paypal'])],
            'terms' => ['accepted'],
        ]);

        $sessionToken = (string) session('checkout_token', '');
        abort_unless($sessionToken !== '' && hash_equals($sessionToken, $data['checkout_token']), 419);

        $order = $this->orders->place($data);

        if (! $order->wasRecentlyCreated) {
            return $this->resumeCheckout($order);
        }

        if ($data['payment_method'] === 'cod') {
            $this->cart->clear();
            session()->forget('checkout_token');
            $this->notify($order);

            return $this->successRedirect($order)->with('success', 'Your order has been placed. Payment is due on delivery.');
        }

        if (config('services.payments.demo')) {
            $order->update(['payment_reference' => 'DEMO-'.strtoupper($data['payment_method']).'-'.$order->id]);
            $this->cart->clear();
            session()->forget('checkout_token');
            $this->notify($order);

            return $this->successRedirect($order)->with('success', 'Demo order placed. No payment was charged; payment remains pending.');
        }

        try {
            $gateway = $data['payment_method'] === 'stripe' ? $stripe : $paypal;
            if (! $gateway->configured()) {
                throw new \RuntimeException(ucfirst($data['payment_method']).' is not configured.');
            }
            session()->put('pending_order', $order->number);
            $paymentUrl = $gateway->checkout($order);
            session()->put('pending_payment_url', $paymentUrl);

            return redirect()->away($paymentUrl);
        } catch (\Throwable $exception) {
            report($exception);
            session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);
            $this->orders->cancel($order, 'Payment session could not be started');

            return redirect()->route('checkout.index')->withErrors(['payment_method' => 'Payment could not be started. Please try again or choose cash on delivery.']);
        }
    }

    public function gatewaySuccess(Request $request, string $provider, Order $order, StripeGateway $stripe, PayPalGateway $paypal): RedirectResponse
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        abort_unless($order->payment_method === $provider, 404);
        $this->ensurePendingOrder($order);
        if ($order->status === 'cancelled') {
            return redirect()->route('cart.index')->withErrors(['payment' => 'This order was cancelled.']);
        }

        try {
            $confirmed = $provider === 'stripe'
                ? ($request->filled('session_id') && $stripe->confirm($request->string('session_id')->toString(), $order))
                : $paypal->capture($order);
        } catch (\Throwable $exception) {
            report($exception);
            $confirmed = false;
        }

        if (! $confirmed) {
            return redirect()->route('checkout.index')->withErrors(['payment' => 'We could not confirm the payment. Your order is still pending.']);
        }

        $paymentResult = $this->orders->markPaid($order);
        $this->cart->clear();
        session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);

        if ($paymentResult === 'cancelled') {
            Log::critical('Payment confirmed after order cancellation', ['order' => $order->number]);

            return redirect()->route('cart.index')->withErrors(['payment' => 'Payment was received after this order was cancelled. Please contact support for a refund.']);
        }

        if ($paymentResult === 'paid') {
            $this->notify($order);
        }

        return $this->successRedirect($order)->with('success', 'Payment confirmed. Thank you for your order.');
    }

    public function gatewayCancel(string $provider, Order $order, StripeGateway $stripe): RedirectResponse
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        abort_unless($order->payment_method === $provider, 404);
        $this->ensurePendingOrder($order);

        if ($provider === 'paypal') {
            return redirect()->route('cart.index')->withErrors([
                'payment' => 'PayPal checkout was closed. Your reserved order remains pending until PayPal expires it; reopen the original payment window or wait for the reservation to release.',
            ]);
        }

        try {
            if (! $stripe->expire($order)) {
                return redirect()->route('cart.index')->withErrors([
                    'payment' => 'We could not safely expire the Stripe session, so the order remains reserved. Please reopen payment or contact support.',
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('cart.index')->withErrors([
                'payment' => 'We could not safely expire the Stripe session, so the order remains reserved. Please try again shortly.',
            ]);
        }

        try {
            $this->orders->cancel($order, 'Customer cancelled payment');
        } catch (ValidationException) {
            session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);

            return $this->successRedirect($order->refresh())->withErrors(['payment' => 'Payment was already confirmed, so the order was not cancelled.']);
        }

        session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);

        return redirect()->route('cart.index')->withErrors(['payment' => 'Payment was cancelled. Your cart has been kept.']);
    }

    public function success(Order $order): View
    {
        return view('checkout.success', ['order' => $order->load('items')]);
    }

    public function stripeWebhook(Request $request, StripeGateway $stripe)
    {
        $event = $stripe->webhookEvent($request);
        if (! $event) {
            return response('Invalid signature', 400);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $number = data_get($event, 'data.object.client_reference_id');
            $order = Order::where('number', $number)->first();
            $reference = (string) data_get($event, 'data.object.id');
            $amount = (int) data_get($event, 'data.object.amount_total', -1);
            $currency = strtolower((string) data_get($event, 'data.object.currency'));
            $isMatchingPayment = $order
                && $order->payment_method === 'stripe'
                && $order->payment_reference === $reference
                && data_get($event, 'data.object.payment_status') === 'paid'
                && $amount === (int) round((float) $order->total * 100)
                && $currency === strtolower($order->currency);

            if ($isMatchingPayment) {
                $paymentResult = $this->orders->markPaid($order, $reference);

                if ($paymentResult === 'cancelled') {
                    Log::critical('Stripe payment completed for a cancelled order', ['order' => $order->number]);
                }

                if ($paymentResult === 'paid') {
                    $this->notify($order);
                }
            }
        }

        return response('ok');
    }

    public function paypalWebhook(Request $request, PayPalGateway $paypal)
    {
        try {
            if (! $paypal->validWebhook($request)) {
                return response('Invalid signature', 400);
            }
        } catch (\Throwable $exception) {
            Log::warning('PayPal webhook verification failed', ['message' => $exception->getMessage()]);

            return response('Verification failed', 400);
        }

        if ($request->input('event_type') === 'PAYMENT.CAPTURE.COMPLETED') {
            $reference = $request->input('resource.supplementary_data.related_ids.order_id');
            $order = Order::where('payment_reference', $reference)->first();
            $amount = number_format((float) $request->input('resource.amount.value', -1), 2, '.', '');
            $currency = strtoupper((string) $request->input('resource.amount.currency_code'));
            $isMatchingPayment = $order
                && $order->payment_method === 'paypal'
                && $request->input('resource.status') === 'COMPLETED'
                && $amount === number_format((float) $order->total, 2, '.', '')
                && $currency === strtoupper($order->currency);

            if ($isMatchingPayment) {
                $paymentResult = $this->orders->markPaid($order, (string) $reference);

                if ($paymentResult === 'cancelled') {
                    Log::critical('PayPal payment completed for a cancelled order', ['order' => $order->number]);
                }

                if ($paymentResult === 'paid') {
                    $this->notify($order);
                }
            }
        }

        return response('ok');
    }

    private function notify(Order $order): void
    {
        try {
            Mail::to($order->customer_email)->queue(new OrderPlaced($order));
        } catch (\Throwable $exception) {
            Log::warning('Order email could not be queued', ['order' => $order->number, 'message' => $exception->getMessage()]);
        }
    }

    private function successRedirect(Order $order): RedirectResponse
    {
        return redirect()->to(URL::temporarySignedRoute('checkout.success', now()->addHours(24), ['order' => $order]));
    }

    private function ensurePendingOrder(Order $order): void
    {
        $pendingOrder = (string) session('pending_order', '');
        abort_unless($pendingOrder !== '' && hash_equals($pendingOrder, $order->number), 403);
    }

    private function resumeCheckout(Order $order): RedirectResponse
    {
        if ($order->status === 'cancelled') {
            session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);

            return redirect()->route('checkout.index')->withErrors(['payment' => 'The previous payment attempt expired or was cancelled. Please submit checkout again.']);
        }

        if ($order->payment_method === 'cod' || $order->payment_status === 'paid' || $order->status !== 'pending') {
            $this->cart->clear();
            session()->forget(['pending_order', 'pending_payment_url', 'checkout_token']);

            return $this->successRedirect($order)->with('success', 'This checkout was already submitted; no duplicate order was created.');
        }

        $pendingOrder = (string) session('pending_order', '');
        $paymentUrl = (string) session('pending_payment_url', '');
        if ($paymentUrl !== '' && hash_equals($pendingOrder, $order->number)) {
            return redirect()->away($paymentUrl);
        }

        return redirect()->route('checkout.index')->withErrors(['payment' => 'Payment is already in progress for this checkout. Please use the original payment window or wait for it to expire.']);
    }
}
