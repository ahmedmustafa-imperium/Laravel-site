<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'orders' => Order::count(),
            'customers' => User::where('is_admin', false)->count(),
            'low_stock' => Product::whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'messages' => ContactMessage::where('is_read', false)->count(),
        ];
        $recentOrders = Order::latest()->limit(8)->get();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'lowStockProducts'));
    }
}
