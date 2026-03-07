<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vận Đơn - {{ $shipping->tracking_number }}</title>
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
        
        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        
        .company-info {
            text-align: right;
            font-size: 11px;
        }
        
        .tracking-section {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border: 2px dashed #64748b;
        }
        
        .tracking-number {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        
        .shipping-info {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        
        .info-block {
            width: 48%;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        
        .info-title {
            font-weight: bold;
            font-size: 14px;
            color: #1e40af;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row {
            margin: 8px 0;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        
        .details-section {
            margin: 20px 0;
            padding: 15px;
            background: #fafafa;
            border-radius: 5px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }
        
        .payment-info {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #059669;
            border-radius: 5px;
            background: #ecfdf5;
        }
        
        .payment-info.cod {
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 10px;
            color: #64748b;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-container {
                margin: 0;
                padding: 5mm;
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
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ In Vận Đơn</button>
    
    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                {{ config('app.name', 'BanHangPro') }}
            </div>
            <div class="company-info">
                <div><strong>PHIẾU GIAO HÀNG</strong></div>
                <div>Ngày tạo: {{ $shipping->created_at->format('d/m/Y H:i') }}</div>
                <div>Đơn hàng: #{{ $shipping->order->code }}</div>
            </div>
        </div>

        <!-- Tracking Number -->
        <div class="tracking-section">
            <div class="tracking-number">{{ $shipping->tracking_number }}</div>
            <div>Mã vận đơn</div>
        </div>

        <!-- Shipping Information -->
        <div class="shipping-info">
            <!-- Người gửi -->
            <div class="info-block">
                <div class="info-title">📦 THÔNG TIN NGƯỜI GỬI</div>
                <div class="info-row">
                    <span class="info-label">Công ty:</span>
                    {{ config('app.name', 'BanHangPro') }}
                </div>
                @if($shipping->pickup_address)
                <div class="info-row">
                    <span class="info-label">Địa chỉ:</span>
                    {{ $shipping->pickup_address }}
                </div>
                @endif
                @if($shipping->pickup_phone)
                <div class="info-row">
                    <span class="info-label">Điện thoại:</span>
                    {{ $shipping->pickup_phone }}
                </div>
                @endif
            </div>

            <!-- Người nhận -->
            <div class="info-block">
                <div class="info-title">📋 THÔNG TIN NGƯỜI NHẬN</div>
                <div class="info-row">
                    <span class="info-label">Họ tên:</span>
                    {{ $shipping->delivery_contact }}
                </div>
                <div class="info-row">
                    <span class="info-label">Điện thoại:</span>
                    {{ $shipping->delivery_phone }}
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ:</span>
                    {{ $shipping->delivery_address }}
                </div>
                @if($shipping->order->customer)
                <div class="info-row">
                    <span class="info-label">Khách hàng:</span>
                    {{ $shipping->order->customer->name }}
                </div>
                @endif
            </div>
        </div>

        <!-- Chi tiết vận chuyển -->
        <div class="details-section">
            <div class="info-title">📋 CHI TIẾT VẬN CHUYỂN</div>
            <div class="details-grid">
                <div>
                    <div class="info-row">
                        <span class="info-label">Đơn vị:</span>
                        {{ $shipping->provider->name ?? 'Giao hàng tận nơi' }}
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phương thức:</span>
                        {{ $shipping->shipping_method }}
                    </div>
                    @if($shipping->weight > 0)
                    <div class="info-row">
                        <span class="info-label">Khối lượng:</span>
                        {{ number_format($shipping->weight, 2) }} kg
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
                        <span class="info-label">Tiền COD:</span>
                        {{ number_format($shipping->cod_amount, 0, ',', '.') }}đ
                    </div>
                    @endif
                    @if($shipping->dimensions)
                    <div class="info-row">
                        <span class="info-label">Kích thước:</span>
                        {{ $shipping->dimensions }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Thông tin thanh toán -->
        <div class="payment-info {{ $shipping->payment_by === 'receiver' ? 'cod' : '' }}">
            <div class="info-title">
                💳 THANH TOÁN VẬN CHUYỂN
            </div>
            <div style="font-size: 14px; font-weight: bold; margin-top: 10px;">
                @if($shipping->payment_by === 'sender')
                    ✅ Người gửi đã thanh toán phí vận chuyển: {{ number_format($shipping->shipping_fee, 0, ',', '.') }}đ
                @else
                    ⚠️ Thu tiền vận chuyển từ người nhận: {{ number_format($shipping->shipping_fee, 0, ',', '.') }}đ
                @endif
            </div>
        </div>

        @if($shipping->note)
        <!-- Ghi chú -->
        <div class="details-section">
            <div class="info-title">📝 GHI CHÚ</div>
            <div style="margin-top: 10px;">{{ $shipping->note }}</div>
        </div>
        @endif

        <!-- Chữ ký -->
        <div class="signature-section">
            <div class="signature-box">
                <div>Người gửi</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
            <div class="signature-box">
                <div>Người vận chuyển</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
            <div class="signature-box">
                <div>Người nhận</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>{{ config('app.name', 'BanHangPro') }} - Hệ thống quản lý bán hàng</div>
            <div>In lúc: {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>

    <script>
        // Auto print when loaded
        window.onload = function() {
            // Uncomment below line if you want auto print
            // window.print();
        }
    </script>
</body>
</html>