<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In Nhiều Vận Đơn - {{ count($shippings) }} đơn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .label-container {
            width: 210mm;
            margin: 0 auto;
            background: white;
        }
        
        .label {
            width: 100%;
            min-height: 148mm;
            padding: 5mm;
            border: 1px solid #ddd;
            margin-bottom: 5mm;
            page-break-after: always;
            position: relative;
        }
        
        .label:last-child {
            page-break-after: auto;
        }
        
        .label-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        
        .logo {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
        }
        
        .order-info {
            text-align: right;
            font-size: 10px;
        }
        
        .tracking-section {
            text-align: center;
            margin: 12px 0;
            padding: 10px;
            background: #f8fafc;
            border: 1px dashed #64748b;
        }
        
        .tracking-number {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .shipping-info {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
        }
        
        .info-block {
            width: 48%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
        }
        
        .info-title {
            font-weight: bold;
            font-size: 12px;
            color: #1e40af;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row {
            margin: 6px 0;
            font-size: 11px;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 70px;
        }
        
        .details-section {
            margin: 12px 0;
            padding: 8px;
            background: #fafafa;
            border-radius: 3px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }
        
        .payment-info {
            margin: 12px 0;
            padding: 8px;
            border: 2px solid #059669;
            border-radius: 3px;
            background: #ecfdf5;
            font-size: 11px;
        }
        
        .payment-info.cod {
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .label-footer {
            position: absolute;
            bottom: 5mm;
            left: 5mm;
            right: 5mm;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .label-container {
                margin: 0;
            }
            
            .label {
                margin-bottom: 0;
                border: none;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #1d4ed8;
        }
        
        .bulk-header {
            text-align: center;
            padding: 15px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ In {{ count($shippings) }} Vận Đơn</button>
    
    <div class="bulk-header no-print">
        <h2>IN NHIỀU VẬN ĐƠN</h2>
        <p>Tổng số: {{ count($shippings) }} vận đơn | In lúc: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
    
    <div class="label-container">
        @foreach($shippings as $shipping)
        <div class="label">
            <!-- Header -->
            <div class="label-header">
                <div class="logo">
                    {{ config('app.name', 'BanHangPro') }}
                </div>
                <div class="order-info">
                    <div><strong>PHIẾU GIAO HÀNG</strong></div>
                    <div>{{ $shipping->created_at->format('d/m/Y') }}</div>
                    <div>Đơn: #{{ $shipping->order->code }}</div>
                </div>
            </div>

            <!-- Tracking Number -->
            <div class="tracking-section">
                <div class="tracking-number">{{ $shipping->tracking_number }}</div>
                <div style="font-size: 10px;">Mã vận đơn</div>
            </div>

            <!-- Shipping Information -->
            <div class="shipping-info">
                <!-- Người gửi -->
                <div class="info-block">
                    <div class="info-title">📦 NGƯỜI GỬI</div>
                    <div class="info-row">
                        <span class="info-label">Công ty:</span>
                        {{ config('app.name') }}
                    </div>
                    @if($shipping->pickup_phone)
                    <div class="info-row">
                        <span class="info-label">ĐT:</span>
                        {{ $shipping->pickup_phone }}
                    </div>
                    @endif
                </div>

                <!-- Người nhận -->
                <div class="info-block">
                    <div class="info-title">📋 NGƯỜI NHẬN</div>
                    <div class="info-row">
                        <span class="info-label">Tên:</span>
                        {{ $shipping->delivery_contact }}
                    </div>
                    <div class="info-row">
                        <span class="info-label">ĐT:</span>
                        {{ $shipping->delivery_phone }}
                    </div>
                </div>
            </div>

            <!-- Địa chỉ nhận -->
            <div class="details-section">
                <div class="info-title">📍 ĐỊA CHỈ GIAO HÀNG</div>
                <div style="margin-top: 5px; font-weight: bold;">
                    {{ $shipping->delivery_address }}
                </div>
            </div>

            <!-- Chi tiết -->
            <div class="details-section">
                <div class="details-grid">
                    <div>
                        <div class="info-row">
                            <span class="info-label">Đơn vị:</span>
                            {{ $shipping->provider->name ?? 'Tận nơi' }}
                        </div>
                        @if($shipping->weight > 0)
                        <div class="info-row">
                            <span class="info-label">Khối lượng:</span>
                            {{ number_format($shipping->weight, 1) }}kg
                        </div>
                        @endif
                    </div>
                    <div>
                        <div class="info-row">
                            <span class="info-label">Phí ship:</span>
                            {{ number_format($shipping->shipping_fee, 0, ',', '.') }}đ
                        </div>
                        @if($shipping->cod_amount > 0)
                        <div class="info-row">
                            <span class="info-label">COD:</span>
                            {{ number_format($shipping->cod_amount, 0, ',', '.') }}đ
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Thanh toán -->
            <div class="payment-info {{ $shipping->payment_by === 'receiver' ? 'cod' : '' }}">
                @if($shipping->payment_by === 'sender')
                    ✅ Phí ship đã thanh toán: {{ number_format($shipping->shipping_fee, 0, ',', '.') }}đ
                @else
                    ⚠️ Thu phí ship: {{ number_format($shipping->shipping_fee, 0, ',', '.') }}đ
                @endif
            </div>

            @if($shipping->note)
            <div class="details-section">
                <div class="info-title">📝 GHI CHÚ</div>
                <div style="margin-top: 5px; font-size: 10px;">{{ $shipping->note }}</div>
            </div>
            @endif

            <!-- Footer -->
            <div class="label-footer">
                Người nhận ký xác nhận: _________________ | 
                Ngày: {{ now()->format('d/m/Y') }} | 
                {{ config('app.name', 'BanHangPro') }}
            </div>
        </div>
        @endforeach
    </div>

    <script>
        window.onload = function() {
            // Auto print when loaded (uncomment if needed)
            // window.print();
        }
    </script>
</body>
</html>