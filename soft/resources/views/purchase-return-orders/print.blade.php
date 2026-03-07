<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Trả Hàng - {{ $order->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .order-code {
            font-size: 14px;
            color: #666;
        }
        
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-box {
            width: 48%;
        }
        
        .info-box h3 {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        
        .total-row {
            margin-bottom: 5px;
        }
        
        .total-amount {
            font-size: 14px;
            font-weight: bold;
            color: #d63384;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 30%;
            text-align: center;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 50px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 10px;
            padding-top: 5px;
            font-style: italic;
        }
        
        .notes {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d1ecf1; color: #0c5460; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        
        @media print {
            body { print-color-adjust: exact; }
            .print-container { margin: 0; padding: 15px; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">CÔNG TY CỔ PHẦN BÁN HÀNG PRO</div>
            <div>Địa chỉ: 123 Đường ABC, Quận XYZ, TP. Hồ Chí Minh</div>
            <div>Điện thoại: (028) 1234 5678 | Email: info@banhangpro.com</div>
            <div class="document-title">ĐơN TRẢ HÀNG CHO NHÀ CUNG CẤP</div>
            <div class="order-code">{{ $order->code }}</div>
            <div style="margin-top: 10px;">
                <span class="status-badge status-{{ $order->status }}">
                    @switch($order->status)
                        @case('pending') Chờ duyệt @break
                        @case('approved') Đã duyệt @break
                        @case('completed') Hoàn thành @break
                        @case('cancelled') Đã hủy @break
                        @default {{ $order->status }}
                    @endswitch
                </span>
            </div>
        </div>

        <!-- Thông tin cơ bản -->
        <div class="info-section">
            <div class="info-box">
                <h3>Thông tin nhà cung cấp</h3>
                <div class="info-item">
                    <span class="label">Tên nhà cung cấp:</span> {{ $order->supplier->name ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Mã nhà cung cấp:</span> {{ $order->supplier->code ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Điện thoại:</span> {{ $order->supplier->phone ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Email:</span> {{ $order->supplier->email ?? 'N/A' }}
                </div>
            </div>
            
            <div class="info-box">
                <h3>Thông tin đơn trả hàng</h3>
                <div class="info-item">
                    <span class="label">Ngày tạo:</span> {{ $order->created_at->format('d/m/Y H:i') }}
                </div>
                <div class="info-item">
                    <span class="label">Ngày trả hàng:</span> {{ $order->returned_at ? $order->returned_at->format('d/m/Y') : 'Chưa xác định' }}
                </div>
                <div class="info-item">
                    <span class="label">Kho trả hàng:</span> {{ $order->warehouse->name ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Người tạo:</span> {{ $order->creator->name ?? 'N/A' }}
                </div>
                @if($order->approver)
                <div class="info-item">
                    <span class="label">Người duyệt:</span> {{ $order->approver->name }}
                </div>
                @endif
            </div>
        </div>

        <!-- Bảng sản phẩm -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">STT</th>
                    <th width="35%">Tên sản phẩm</th>
                    <th width="15%">Mã SKU</th>
                    <th width="10%">Số lượng</th>
                    <th width="15%">Đơn giá</th>
                    <th width="15%">Thành tiền</th>
                    <th width="5%">Lý do</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product->name ?? 'N/A' }}</strong>
                        @if($item->lot_number)
                        <br><small>Lô: {{ $item->lot_number }}</small>
                        @endif
                    </td>
                    <td>{{ $item->product->sku ?? 'N/A' }}</td>
                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                    <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}đ</td>
                    <td class="text-right">{{ number_format($item->total, 0, ',', '.') }}đ</td>
                    <td class="text-center">{{ $item->return_reason ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Không có sản phẩm nào</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Tổng tiền -->
        <div class="total-section">
            <div class="total-row">
                <strong>Tổng số lượng: {{ number_format($order->items->sum('quantity')) }}</strong>
            </div>
            <div class="total-row total-amount">
                <strong>Tổng tiền trả: {{ number_format($order->total, 0, ',', '.') }}đ</strong>
            </div>
        </div>

        <!-- Ghi chú -->
        @if($order->note)
        <div class="notes">
            <strong>Ghi chú:</strong>
            <div>{{ $order->note }}</div>
        </div>
        @endif

        <!-- Chữ ký -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-title">Người lập đơn</div>
                <div class="signature-line">{{ $order->creator->name ?? '' }}</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">Người duyệt</div>
                <div class="signature-line">{{ $order->approver->name ?? '(Ký và ghi rõ họ tên)' }}</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">Thủ kho</div>
                <div class="signature-line">(Ký và ghi rõ họ tên)</div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; font-size: 11px; color: #666;">
            <div>Ngày in: {{ now()->format('d/m/Y H:i:s') }}</div>
            <div>Đơn trả hàng được tạo bởi hệ thống BánHàngPro</div>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        }
        
        // Close window after printing
        window.onafterprint = function() {
            window.close();
        }
    </script>
</body>
</html>