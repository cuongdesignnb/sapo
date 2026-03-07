<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng {{ $order->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #333;
            background: #fff;
            margin: 0;
            padding: 15mm;
        }
        
        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }
        
        .header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }
        
        .header .order-code {
            font-size: 20px;
            font-weight: 500;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .header .order-date {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 8px;
            position: relative;
            z-index: 1;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            position: relative;
        }
        
        .info-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 8px 8px 0 0;
        }
        
        .info-box h3 {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .info-box h3::before {
            content: '👤';
            margin-right: 8px;
            font-size: 18px;
        }
        
        .info-box:nth-child(2) h3::before {
            content: '📋';
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 100px;
            margin-right: 12px;
        }
        
        .info-value {
            color: #2d3748;
            flex: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confirmed {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .status-shipping {
            background: #bee3f8;
            color: #2a69ac;
        }
        
        .items-section {
            margin: 30px 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title::before {
            content: '📦';
            margin-right: 8px;
            font-size: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .items-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            font-weight: 600;
            text-align: center;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .items-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
            font-weight: 600;
        }
        
        .product-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .product-sku {
            font-size: 11px;
            color: #718096;
            font-family: 'Courier New', monospace;
        }
        
        .summary-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            min-width: 300px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 16px;
            color: #2d3748;
            border-top: 2px solid #667eea;
            margin-top: 8px;
            padding-top: 12px;
        }
        
        .summary-row.debt {
            color: #e53e3e;
            font-weight: 600;
        }
        
        .summary-row.paid {
            color: #38a169;
            font-weight: 600;
        }
        
        .notes-section {
            margin-top: 30px;
            padding: 20px;
            background: #fffbf0;
            border: 1px solid #f6e05e;
            border-radius: 8px;
        }
        
        .notes-section h4 {
            color: #744210;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .notes-section h4::before {
            content: '📝';
            margin-right: 8px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-box h4 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 40px;
        }
        
        .signature-line {
            border-top: 2px solid #2d3748;
            width: 200px;
            margin: 0 auto;
            padding-top: 8px;
            font-weight: 600;
        }
        
        .print-info {
            text-align: center;
            margin-top: 30px;
            font-size: 11px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        
        @media print {
            body {
                font-size: 12px;
                padding: 0;
            }
            
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .items-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <h1>ĐƠN HÀNG</h1>
            <div class="order-code">{{ $order->code }}</div>
            <div class="order-date">
                Ngày tạo: {{ $order->created_at->format('d/m/Y H:i') }}
            </div>
        </div>

        <div class="content">
            <!-- Info Section -->
            <div class="info-section">
                <!-- Thông tin khách hàng -->
                <div class="info-box">
                    <h3>Thông tin khách hàng</h3>
                    <div class="info-row">
                        <span class="info-label">Tên:</span>
                        <span class="info-value">{{ $order->customer->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Mã KH:</span>
                        <span class="info-value">{{ $order->customer->code ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">SĐT:</span>
                        <span class="info-value">{{ $order->customer->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">{{ $order->customer->email ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Địa chỉ:</span>
                        <span class="info-value">{{ $order->delivery_address ?? 'N/A' }}</span>
                    </div>
                </div>

                <!-- Thông tin đơn hàng -->
                <div class="info-box">
                    <h3>Thông tin đơn hàng</h3>
                    <div class="info-row">
                        <span class="info-label">Kho:</span>
                        <span class="info-value">{{ $order->warehouse->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Địa chỉ kho:</span>
                        <span class="info-value">{{ $order->warehouse->address ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nhân viên:</span>
                        <span class="info-value">{{ $order->cashier->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Trạng thái:</span>
                        <span class="info-value">
                            <span class="status-badge status-{{ $order->status }}">
                                {{ 
                                    $order->status === 'pending' ? 'Chờ xử lý' :
                                    ($order->status === 'confirmed' ? 'Đã xác nhận' :
                                    ($order->status === 'shipping' ? 'Đang giao hàng' :
                                    ($order->status === 'completed' ? 'Hoàn thành' :
                                    ($order->status === 'cancelled' ? 'Đã hủy' : $order->status))))
                                }}
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nguồn:</span>
                        <span class="info-value">{{ $order->source ?? 'Web' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ưu tiên:</span>
                        <span class="info-value">
                            {{ 
                                $order->priority === 'high' ? '🔴 Cao' :
                                ($order->priority === 'urgent' ? '🚨 Khẩn cấp' :
                                ($order->priority === 'low' ? '🟢 Thấp' : '🟡 Bình thường'))
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="items-section">
                <h3 class="section-title">Chi tiết sản phẩm</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">STT</th>
                            <th style="width: 20%">Mã sản phẩm</th>
                            <th style="width: 35%">Tên sản phẩm</th>
                            <th style="width: 10%">Số lượng</th>
                            <th style="width: 15%">Đơn giá</th>
                            <th style="width: 15%">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>
                                <div class="product-sku">{{ $item->sku ?? $item->product->sku ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div class="product-name">{{ $item->product_name ?? $item->product->name ?? 'N/A' }}</div>
                            </td>
                            <td class="text-center">{{ number_format($item->quantity) }}</td>
                            <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}₫</td>
                            <td class="text-right">{{ number_format($item->total, 0, ',', '.') }}₫</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Tổng tiền hàng:</span>
                        <span>{{ number_format($order->total, 0, ',', '.') }}₫</span>
                    </div>
                    <div class="summary-row">
                        <span>Chiết khấu:</span>
                        <span>{{ number_format($order->discount_amount ?? 0, 0, ',', '.') }}₫</span>
                    </div>
                    <div class="summary-row">
                        <span>VAT ({{ $order->vat_percent ?? 0 }}%):</span>
                        <span>{{ number_format($order->vat_amount ?? 0, 0, ',', '.') }}₫</span>
                    </div>
                    <div class="summary-row total">
                        <span>Tổng cộng:</span>
                        <span>{{ number_format($order->total, 0, ',', '.') }}₫</span>
                    </div>
                    <div class="summary-row paid">
                        <span>Đã thanh toán:</span>
                        <span>{{ number_format($order->paid, 0, ',', '.') }}₫</span>
                    </div>
                    <div class="summary-row debt">
                        <span>Còn nợ:</span>
                        <span>{{ number_format($order->debt, 0, ',', '.') }}₫</span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($order->note)
            <div class="notes-section">
                <h4>Ghi chú</h4>
                <div>{{ $order->note }}</div>
            </div>
            @endif

            <!-- Footer -->
            <div class="footer">
                <div class="signature-box">
                    <h4>Người bán hàng</h4>
                    <div class="signature-line">{{ $order->cashier->name ?? 'N/A' }}</div>
                </div>
                <div class="signature-box">
                    <h4>Khách hàng</h4>
                    <div class="signature-line">{{ $order->customer->name ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Print Info -->
            <div class="print-info">
                <div>📄 Đơn hàng được in lúc: {{ now()->format('d/m/Y H:i:s') }}</div>
                <div>🏢 Hệ thống quản lý bán hàng</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>