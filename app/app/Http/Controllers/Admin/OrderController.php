<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner->where('number', 'like', '%'.$request->q.'%')->orWhere('customer_email', 'like', '%'.$request->q.'%')->orWhere('customer_name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load(['items', 'user'])]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $allowedStatuses = match ($order->status) {
            'pending' => ['pending', 'processing', 'cancelled'],
            'processing' => ['processing', 'shipped', 'completed', 'cancelled'],
            'shipped' => ['shipped', 'completed'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
            default => [$order->status],
        };
        $allowedPaymentStatuses = match ($order->payment_status) {
            'pending' => ['pending', 'paid', 'failed'],
            'failed' => ['failed', 'pending', 'paid'],
            'paid' => ['paid', 'refunded'],
            'refunded' => ['refunded'],
            default => [$order->payment_status],
        };

        $data = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
            'payment_status' => ['required', Rule::in($allowedPaymentStatuses)],
            'refund_confirmed' => $request->input('payment_status') === 'refunded'
                ? ['required', 'accepted']
                : ['nullable'],
        ]);

        if ($data['status'] === 'cancelled' && $data['payment_status'] === 'paid') {
            throw ValidationException::withMessages(['status' => 'A paid order cannot be cancelled. Complete the provider refund first.']);
        }

        if ($data['payment_status'] === 'failed' && ! in_array($data['status'], ['pending', 'cancelled'], true)) {
            throw ValidationException::withMessages(['payment_status' => 'A failed payment cannot be fulfilled or shipped.']);
        }

        if ($data['status'] === 'completed' && ! in_array($data['payment_status'], ['paid', 'refunded'], true)) {
            throw ValidationException::withMessages(['payment_status' => 'A completed order must be paid.']);
        }

        if ($data['payment_status'] === 'paid' && $data['status'] === 'pending') {
            $data['status'] = 'processing';
        }

        if ($data['status'] === 'cancelled' && $order->status !== 'cancelled') {
            $this->orders->cancel($order, 'Cancelled by administrator');
        } elseif ($order->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()->withErrors(['status' => 'A cancelled order cannot be reopened because its inventory was restored.']);
        }

        $order->update([
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'paid_at' => $data['payment_status'] === 'paid' ? ($order->paid_at ?? now()) : $order->paid_at,
            'expires_at' => $data['status'] === 'pending' && $data['payment_status'] === 'pending' ? $order->expires_at : null,
        ]);

        return back()->with('success', 'Order updated.');
    }
}
