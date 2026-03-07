<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return view('orders.index');
    }

    public function create()
    {
        $customers = Customer::where('status', 'active')->get();
        $warehouses = Warehouse::where('status', 'active')->get();
        
        return view('orders.create', compact('customers', 'warehouses'));
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'warehouse', 'items.product'])->findOrFail($id);
        
        return view('orders.show', compact('order'));
    }
}