<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturnOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseReturnOrderController extends Controller
{
    /**
     * Display a listing of purchase return orders
     */
    public function index(): View
    {
        return view('purchase-return-orders.index');
    }

    /**
     * Show the form for creating a new purchase return order
     */
    public function create(): View
    {
        return view('purchase-return-orders.create');
    }

    /**
     * Display the specified purchase return order
     */
    public function show(string $id): View
    {
        return view('purchase-return-orders.show', compact('id'));
    }

    /**
     * Show the form for editing the specified purchase return order
     */
    public function edit(string $id): View
    {
        return view('purchase-return-orders.edit', compact('id'));
    }

    /**
     * Print the purchase return order
     */
    public function print(string $id): View
    {
        $order = PurchaseReturnOrder::with([
            'supplier',
            'warehouse',
            'creator',
            'approver',
            'items.product'
        ])->findOrFail($id);

        return view('purchase-return-orders.print', compact('order'));
    }
}