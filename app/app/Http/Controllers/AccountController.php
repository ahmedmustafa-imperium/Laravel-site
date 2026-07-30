<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return view('account.index', compact('orders'));
    }

    public function update(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $emailIsChanging = $request->input('email') !== $request->user()->email;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'current_password' => [Rule::requiredIf($emailIsChanging), 'nullable', 'current_password'],
        ]);
        unset($data['current_password']);
        $data['marketing_opt_in'] = $request->boolean('marketing_opt_in');
        $request->user()->update($data);

        return back()->with('success', 'Account details updated.');
    }

    public function order(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('account.order', ['order' => $order->load('items')]);
    }
}
