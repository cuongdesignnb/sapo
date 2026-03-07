<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\PurchaseReturnReceipt;

class PurchaseReturnReceiptController extends Controller
{
    public function index(): View
    {
        return view('purchase-return-receipts.index');
    }

    public function create(): View
    {
        return view('purchase-return-receipts.create');
    }

    public function show(string $id): View
    {
        return view('purchase-return-receipts.show', compact('id'));
    }
    public function print(string $id): View
{
    $receipt = PurchaseReturnReceipt::with([
        'purchaseReturnOrder.supplier',
        'supplier',
        'warehouse',
        'returnedBy',
        'creator',
        'items.product'
    ])->findOrFail($id);

    return view('purchase-return-receipts.print', compact('receipt'));
}
}