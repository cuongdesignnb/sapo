<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn - {{ $order->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 12px; line-height: 1.4; }
        .invoice { max-width: 300px; margin: 0 auto; padding: 10px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .company-name { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
        .company-info { font-size: 10px; }
        .invoice-title { font-size: 14px; font-weight: bold; margin: 10px 0; }
        .order-info { margin-bottom: 15px; }
        .order-info div { margin-bottom: 3px; }
        .items-table { width: 100%; margin-bottom: 15px; }
        .items-table th, .items-table td { padding: 3px; text-align: left; }
        .items-table th { border-bottom: 1px solid #000; font-weight: bold; }
        .items-table .qty, .items-table .price, .items-table .total { text-align: right; }
        .summary { border-top: 1px dashed #000; padding-top: 10px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .total-row { font-weight: bold; font-size: 14px; border-top: 1px solid #000; padding-top: 5px; margin-top: 5px; }
        .footer { text-align: center; margin-top: 15px; border-top: 1px dashed #000; padding-top: 10px; font-size: 10px; }
        @media print { body { margin: 0; } .invoice { max-width: none; } }
    </style>
</head>
<body>
    <div class="invoice">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ config('app.name', 'BANHANGPRO') }}</div>
            <div class="company-info">
                {{ $order->warehouse->name ?? 'Cửa hàng' }}<br>
                Hotline: 1900 6750<br>
                Website: banhangpro.com
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title">HÓA ĐƠN BÁN HÀNG</div>

        <!-- Order Info -->
        <div class="order-info">
            <div><strong>Mã HĐ:</strong> {{ $order->code }}</div>
            <div><strong>Ngày:</strong> {{ $order->created_at->format('d/m/Y H:i:s') }}</div>
            <div><strong>Thu ngân:</strong> {{ $order->cashier->name ?? 'N/A' }}</div>
            @if($order->customer && $order->customer->code !== 'KHBLE')
            <div><strong>Khách hàng:</strong> {{ $order->customer->name }}</div>
            <div><strong>SĐT:</strong> {{ $order->customer->phone }}</div>
            @endif
        </div>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th class="qty">SL</th>
                    <th class="price">Giá</th>
                    <th class="total">T.Tiền</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}<br>
                        <small>{{ $item->sku }}</small>
                    </td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="price">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="total">{{ number_format($item->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary">
            @if($order->subtotal && $order->subtotal != $order->total)
            <div class="summary-row">
                <span>Tạm tính:</span>
                <span>{{ number_format($order->subtotal, 0, ',', '.') }}đ</span>
            </div>
            @endif
            
            @if($order->discount_amount > 0)
            <div class="summary-row">
                <span>Chiết khấu 
                    @if($order->discount_percent > 0)
                        ({{ $order->discount_percent }}%)
                    @endif
                :</span>
                <span>-{{ number_format($order->discount_amount, 0, ',', '.') }}đ</span>
            </div>
            @endif
            
            @if($order->vat_amount > 0)
            <div class="summary-row">
                <span>VAT ({{ $order->vat_percent }}%):</span>
                <span>{{ number_format($order->vat_amount, 0, ',', '.') }}đ</span>
            </div>
            @endif
            
            <div class="summary-row total-row">
                <span>TỔNG CỘNG:</span>
                <span>{{ number_format($order->total, 0, ',', '.') }}đ</span>
            </div>
            
            <div class="summary-row">
                <span>Đã thanh toán:</span>
                <span>{{ number_format($order->paid, 0, ',', '.') }}đ</span>
            </div>
            
            @if($order->paid > $order->total)
            <div class="summary-row">
                <span>Tiền thừa:</span>
                <span>{{ number_format($order->paid - $order->total, 0, ',', '.') }}đ</span>
            </div>
            @endif
            
            @if($order->debt > 0)
            <div class="summary-row">
                <span>Còn nợ:</span>
                <span>{{ number_format($order->debt, 0, ',', '.') }}đ</span>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Cảm ơn quý khách!</div>
            <div>Hẹn gặp lại!</div>
            @if($order->note)
            <div style="margin-top: 10px;"><em>{{ $order->note }}</em></div>
            @endif
        </div>
    </div>

    <script>
        // Tự động in khi load trang
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>