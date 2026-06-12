<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Str;

class PrintableOrderService
{
    public function __construct(
        private readonly OrderPaymentSummaryService $paymentSummary
    ) {
    }

    public function forOrder(Order $order): array
    {
        $order->loadMissing([
            'customer',
            'items.product.units',
        ]);

        $summary = $this->paymentSummary->summary($order);
        $items = $order->items->map(function ($item) {
            $quantity = (float) ($item->qty ?? 0);
            $price = (float) ($item->price ?? 0);
            $discount = max(0.0, (float) ($item->discount ?? 0));
            $fallbackTotal = max(0.0, ($quantity * $price) - $discount);
            $storedTotal = (float) ($item->subtotal ?? 0);

            return $this->mapItem(
                $item->product,
                $quantity,
                $price,
                $discount,
                $storedTotal > 0 || $fallbackTotal === 0 ? $storedTotal : $fallbackTotal
            );
        })->values()->all();

        return [
            'code' => $this->text($order->code),
            'code_label' => 'Mã đơn hàng',
            'created_at' => $order->created_at?->format('d-m-Y') ?? '',
            'title' => 'Đơn đặt hàng',
            'customer' => $this->customerData($order),
            'note' => $this->text($order->note),
            'items' => $items,
            'totals' => [
                'total_quantity' => array_sum(array_column($items, 'quantity')),
                'subtotal' => (float) ($order->total_price ?? 0),
                'discount_total' => max(0.0, (float) ($order->discount ?? 0)),
                'other_fees' => max(0.0, (float) ($order->other_fees ?? 0)),
                'delivery_fee' => max(0.0, (float) ($order->delivery_fee ?? 0)),
                'total' => (float) ($summary['order_total'] ?? $order->total_payment ?? 0),
                'customer_must_pay' => (float) ($summary['order_total'] ?? $order->total_payment ?? 0),
                'deposit' => (float) ($summary['original_deposit'] ?? 0),
                'paid' => (float) ($summary['paid_after_deposit'] ?? 0),
                'remaining' => (float) ($summary['order_remaining_debt'] ?? 0),
            ],
            'signatures' => [
                'left_label' => 'Người lập đơn',
                'right_label' => 'Khách hàng',
            ],
        ];
    }

    public function forInvoice(Invoice $invoice): array
    {
        abort_unless($invoice->order_id, 404);

        $invoice->loadMissing([
            'customer',
            'order',
            'items.orderItem',
            'items.product.units',
        ]);

        $items = $invoice->items->map(function ($item) {
            $quantity = (float) ($item->quantity ?? 0);
            $price = (float) ($item->price ?? 0);
            $discount = max(0.0, (float) ($item->discount ?? 0));

            if ($discount <= 0 && $item->orderItem && (float) $item->orderItem->discount > 0) {
                $orderedQuantity = (float) ($item->orderItem->qty ?? 0);
                $discount = $orderedQuantity > 0
                    ? ((float) $item->orderItem->discount / $orderedQuantity) * $quantity
                    : 0.0;
            }

            $fallbackTotal = max(0.0, ($quantity * $price) - $discount);
            $storedTotal = (float) ($item->subtotal ?? 0);

            return $this->mapItem(
                $item->product,
                $quantity,
                $price,
                $discount,
                $storedTotal > 0 || $fallbackTotal === 0 ? $storedTotal : $fallbackTotal
            );
        })->values()->all();

        $total = (float) ($invoice->total ?? 0);
        $deposit = max(0.0, (float) ($invoice->order_deposit_applied_amount ?? 0));
        $totalPaid = max(0.0, (float) ($invoice->customer_paid ?? 0));

        return [
            'code' => $this->text($invoice->code),
            'code_label' => 'Mã hóa đơn',
            'created_at' => $invoice->created_at?->format('d-m-Y') ?? '',
            'title' => 'Hóa đơn bán hàng',
            'customer' => $this->customerData($invoice),
            'note' => $this->text($invoice->note),
            'items' => $items,
            'totals' => [
                'total_quantity' => array_sum(array_column($items, 'quantity')),
                'subtotal' => (float) ($invoice->subtotal ?? 0),
                'discount_total' => max(0.0, (float) ($invoice->discount ?? 0)),
                'other_fees' => max(0.0, (float) ($invoice->other_fees ?? 0)),
                'delivery_fee' => max(0.0, (float) ($invoice->delivery_fee ?? 0)),
                'total' => $total,
                'customer_must_pay' => $total,
                'deposit' => $deposit,
                'paid' => max(0.0, $totalPaid - $deposit),
                'remaining' => max(0.0, $total - $totalPaid),
            ],
            'signatures' => [
                'left_label' => 'Người bán',
                'right_label' => 'Người mua',
            ],
        ];
    }

    private function mapItem($product, float $quantity, float $price, float $discount, float $total): array
    {
        $baseUnit = $product?->units?->firstWhere('is_base_unit', true)
            ?? $product?->units?->first();

        return [
            'sku' => $this->text($product?->sku ?: $product?->barcode),
            'name' => $this->text($product?->name),
            'unit' => $this->text($baseUnit?->unit_name),
            'quantity' => $quantity,
            'price' => $price,
            'discount' => $discount,
            'total' => $total,
        ];
    }

    private function customerData($document): array
    {
        $customer = $document->customer;

        return [
            'name' => $this->text($customer?->name ?: $document->receiver_name ?: 'Khách lẻ'),
            'phone' => $this->text($customer?->phone ?: $document->receiver_phone),
            'email' => $this->text($customer?->email),
            'address' => $this->text(
                $customer?->address ?: $this->receiverAddress($document)
            ),
        ];
    }

    private function receiverAddress($document): string
    {
        return collect([
            $document->receiver_address ?? null,
            $document->receiver_ward ?? null,
            $document->receiver_district ?? null,
            $document->receiver_city ?? null,
        ])->filter(fn ($value) => filled($value))->implode(', ');
    }

    private function text($value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::of((string) $value)->trim()->toString();
    }
}
