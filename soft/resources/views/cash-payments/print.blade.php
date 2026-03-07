<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu Chi - {{ $payment->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background: white;
        }

        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .logo-section {
            display: table-cell;
            width: 20%;
            vertical-align: middle;
            text-align: center;
            padding-right: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            margin: 0 auto 5px;
            overflow: hidden;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .company-section {
            display: table-cell;
            width: 55%;
            vertical-align: middle;
            padding: 0 10px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 10px;
            line-height: 1.4;
            margin-bottom: 2px;
        }

        .payment-section {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
            text-align: center;
            padding-left: 15px;
        }

        .payment-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
            color: #dc3545;
        }

        .payment-code {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .payment-date {
            font-size: 10px;
        }

        .payment-type-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 8px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 10px;
            color: #856404;
        }

        .payment-info {
            margin: 20px 0;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            min-width: 140px;
            padding-right: 10px;
            vertical-align: top;
        }

        .info-value {
            display: table-cell;
            border-bottom: 1px dotted #666;
            padding-bottom: 2px;
            width: 100%;
        }

        .amount-section {
            margin: 25px 0;
            padding: 15px;
            border: 2px solid #dc3545;
            text-align: center;
            background: #fff5f5;
        }

        .amount-label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            color: #dc3545;
        }

        .amount-number {
            font-size: 20px;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 8px;
        }

        .amount-words {
            font-style: italic;
            font-size: 12px;
            text-transform: capitalize;
            color: #333;
        }

        .note-section {
            margin: 20px 0;
        }

        .impact-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            padding: 8px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 10px;
            color: #0066cc;
        }

        .signatures {
            display: table;
            width: 100%;
            margin-top: 30px;
            text-align: center;
        }

        .signature-box {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            padding: 0 10px;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 11px;
        }

        .signature-date {
            font-size: 10px;
            margin-bottom: 40px;
            font-style: italic;
        }

        .signature-name {
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 11px;
            margin: 0 15px;
        }

        .footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #000;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            width: 70%;
            font-size: 10px;
            color: #666;
            vertical-align: top;
        }

        .footer-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            font-size: 10px;
            vertical-align: top;
        }

        .status-badge {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
        }

        .status-approved {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background: #f5c6cb;
            color: #721c24;
        }

        .approval-info {
            margin-top: 5px;
            font-size: 9px;
            color: #666;
        }

        /* Print styles */
        @media print {
            body {
                margin: 0;
                font-size: 11px;
            }
            
            .print-container {
                margin: 0;
                padding: 8mm;
                max-width: none;
            }
            
            .no-print {
                display: none !important;
            }

            .logo {
                width: 45px;
                height: 45px;
                font-size: 12px;
            }

            .amount-section {
                background: #fff5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .warning-box {
                background: #fff3cd !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .impact-info {
                background: #e7f3ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                margin: 10mm;
                size: A4 portrait;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .print-button:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ In phiếu</button>
    
    <div class="print-container">
        <!-- Header với Logo -->
        <div class="header">
            <div class="logo-section">
                <div class="logo">
                    @if(config('company.logo') && file_exists(public_path(config('company.logo'))))
                        <img src="{{ asset(config('company.logo')) }}" alt="Logo">
                    @elseif(file_exists(public_path('images/logo.png')))
                        <img src="{{ asset('images/logo.png') }}" alt="Logo">
                    @else
                        {{ strtoupper(substr(config('company.name', 'COMPANY'), 0, 1)) }}
                    @endif
                </div>
                <div style="font-size: 8px; font-weight: bold;">{{ config('app.name', 'COMPANY') }}</div>
            </div>
            
            <div class="company-section">
                <div class="company-name">{{ config('company.name', $payment->warehouse->company_name ?? 'CÔNG TY CỔ PHẦN ABC') }}</div>
                <div class="company-info">
                    <strong>Địa chỉ:</strong> {{ config('company.address', $payment->warehouse->address ?? 'Địa chỉ công ty') }}
                </div>
                <div class="company-info">
                    <strong>Điện thoại:</strong> {{ config('company.phone', $payment->warehouse->phone ?? '0123456789') }}
                    @if(config('company.email', $payment->warehouse->email))
                    | <strong>Email:</strong> {{ config('company.email', $payment->warehouse->email ?? 'info@company.com') }}
                    @endif
                </div>
                <div class="company-info">
                    @if(config('company.tax_code', $payment->warehouse->tax_code))
                    <strong>MST:</strong> {{ config('company.tax_code', $payment->warehouse->tax_code ?? '0123456789') }}
                    @endif
                    @if(config('company.website'))
                    | <strong>Website:</strong> {{ config('company.website') }}
                    @endif
                </div>
            </div>
            
            <div class="payment-section">
                <div class="payment-title">Phiếu Chi</div>
                <div class="payment-code">Số: {{ $payment->code }}</div>
                <div class="payment-date">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</div>
            </div>
        </div>

        <!-- Payment Type Badge -->
        @if($payment->paymentType)
        <div style="text-align: center; margin-bottom: 15px;">
            <div class="payment-type-badge">{{ $payment->paymentType->name ?? 'N/A' }}</div>
        </div>
        @endif

        <!-- Warning for sensitive payment types -->
        @if(in_array($payment->paymentType->code ?? '', ['CHI_NO_NCC', 'CHI_LUONG', 'CHI_KHAC']))
        <div class="warning-box">
            ⚠️ <strong>Lưu ý:</strong> Đây là phiếu chi quan trọng, vui lòng kiểm tra kỹ thông tin trước khi thanh toán.
        </div>
        @endif

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="info-row">
                <span class="info-label">Họ tên người nhận:</span>
                <span class="info-value">{{ $payment->recipient->name ?? $payment->payee_name ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Địa chỉ:</span>
                <span class="info-value">{{ $payment->recipient->address ?? $payment->payee_address ?? 'N/A' }}</span>
            </div>
            
            @if($payment->recipient->phone ?? $payment->payee_phone)
            <div class="info-row">
                <span class="info-label">Điện thoại:</span>
                <span class="info-value">{{ $payment->recipient->phone ?? $payment->payee_phone }}</span>
            </div>
            @endif
            
            <div class="info-row">
                <span class="info-label">Lý do chi:</span>
                <span class="info-value">{{ $payment->paymentType->name ?? $payment->reason ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Ngày chi:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Hình thức thanh toán:</span>
                <span class="info-value">
                    @if($payment->payment_method === 'cash')
                        Tiền mặt
                    @elseif($payment->payment_method === 'bank_transfer')
                        Chuyển khoản
                    @elseif($payment->payment_method === 'card')
                        Thẻ tín dụng
                    @elseif($payment->payment_method === 'check')
                        Séc
                    @else
                        {{ $payment->payment_method }}
                    @endif
                </span>
            </div>
            
            @if($payment->reference_number)
            <div class="info-row">
                <span class="info-label">Số tham chiếu:</span>
                <span class="info-value">{{ $payment->reference_number }}</span>
            </div>
            @endif

            @if($payment->bank_name || $payment->bank_account)
            <div class="info-row">
                <span class="info-label">Thông tin ngân hàng:</span>
                <span class="info-value">
                    {{ $payment->bank_name ?? '' }} 
                    @if($payment->bank_account)
                        - TK: {{ $payment->bank_account }}
                    @endif
                </span>
            </div>
            @endif

            <div class="info-row">
                <span class="info-label">Chi nhánh/Kho:</span>
                <span class="info-value">{{ $payment->warehouse->name ?? 'N/A' }}</span>
            </div>

            @if($payment->invoice_number || $payment->contract_number)
            <div class="info-row">
                <span class="info-label">Liên quan đến:</span>
                <span class="info-value">
                    @if($payment->invoice_number)
                        Hóa đơn: {{ $payment->invoice_number }}
                    @endif
                    @if($payment->contract_number)
                        - Hợp đồng: {{ $payment->contract_number }}
                    @endif
                </span>
            </div>
            @endif
        </div>

        <!-- Amount Section -->
        <div class="amount-section">
            <div class="amount-label">Số tiền chi trả</div>
            <div class="amount-number">{{ number_format($payment->amount, 0, ',', '.') }} VNĐ</div>
            <div class="amount-words">Bằng chữ: {{ convertNumberToWords($payment->amount) }} đồng</div>
        </div>

        <!-- Note Section -->
        @if($payment->note)
        <div class="note-section">
            <div class="info-row">
                <span class="info-label">Ghi chú:</span>
                <span class="info-value">{{ $payment->note }}</span>
            </div>
        </div>
        @endif

        <!-- Impact Information -->
        @if($payment->paymentType)
        <div class="impact-info">
            <strong>📊 Tác động kế toán:</strong>
            {{ $payment->paymentType->impact_action === 'increase' ? 'Tăng' : 'Giảm' }} 
            {{ 
                $payment->paymentType->impact_type === 'debt' ? 'công nợ' : 
                ($payment->paymentType->impact_type === 'expense' ? 'chi phí' : 
                ($payment->paymentType->impact_type === 'advance' ? 'ứng trước' : 
                ($payment->paymentType->impact_type === 'asset' ? 'tài sản' : 'khác')))
            }}
            của {{ $payment->paymentType->recipient_type === 'customer' ? 'khách hàng' : ($payment->paymentType->recipient_type === 'supplier' ? 'nhà cung cấp' : 'bên thứ ba') }}
        </div>
        @endif

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">Người nhận tiền</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($payment->payment_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($payment->payment_date)->format('m') }} năm {{ \Carbon\Carbon::parse($payment->payment_date)->format('Y') }}</div>
                <div class="signature-name">{{ $payment->recipient->name ?? $payment->payee_name ?? '_________________' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-title">Người chi tiền</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($payment->payment_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($payment->payment_date)->format('m') }} năm {{ \Carbon\Carbon::parse($payment->payment_date)->format('Y') }}</div>
                <div class="signature-name">{{ $payment->creator->name ?? $payment->cashier->name ?? '_________________' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-title">Kế toán trưởng</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($payment->payment_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($payment->payment_date)->format('m') }} năm {{ \Carbon\Carbon::parse($payment->payment_date)->format('Y') }}</div>
                <div class="signature-name">{{ $payment->approver->name ?? '_________________' }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                @if(config('company.hotline'))
                <div><strong>Hotline:</strong> {{ config('company.hotline') }}</div>
                @endif
                @if(config('company.support_email'))
                <div><strong>Email hỗ trợ:</strong> {{ config('company.support_email') }}</div>
                @endif
                <div>Phiếu chi được tạo bởi {{ config('company.name', 'Hệ thống quản lý') }}</div>
            </div>
            <div class="footer-right">
                <div>In lúc: {{ now()->format('d/m/Y H:i:s') }}</div>
                <div style="margin-top: 5px;">
                    Trạng thái: 
                    <span class="status-badge status-{{ $payment->status }}">
                        @if($payment->status === 'approved')
                            ĐÃ DUYỆT
                        @elseif($payment->status === 'pending')
                            CHỜ DUYỆT
                        @elseif($payment->status === 'rejected')
                            TỪ CHỐI
                        @elseif($payment->status === 'paid')
                            ĐÃ CHI
                        @else
                            {{ strtoupper($payment->status) }}
                        @endif
                    </span>
                </div>
                @if($payment->approved_by && $payment->approved_at)
                <div class="approval-info">
                    Duyệt bởi: <strong>{{ $payment->approver->name ?? 'N/A' }}</strong><br>
                    Lúc: {{ \Carbon\Carbon::parse($payment->approved_at)->format('d/m/Y H:i') }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Auto print when page loads (uncomment if needed)
            // setTimeout(function() {
            //     window.print();
            // }, 500);
        }
        
        // Close window after printing (uncomment if needed)
        window.addEventListener('afterprint', function() {
            // window.close();
        });
    </script>
</body>
</html>

@php
function convertNumberToWords($number) {
    if ($number == 0) return 'không';
    
    $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $teens = ['mười', 'mười một', 'mười hai', 'mười ba', 'mười bốn', 'mười lăm', 'mười sáu', 'mười bảy', 'mười tám', 'mười chín'];
    $tens = ['', '', 'hai mười', 'ba mười', 'bốn mười', 'năm mười', 'sáu mười', 'bảy mười', 'tám mười', 'chín mười'];
    $thousands = ['', 'nghìn', 'triệu', 'tỷ'];
    
    if ($number < 10) {
        return $units[$number];
    } elseif ($number < 20) {
        return $teens[$number - 10];
    } elseif ($number < 100) {
        $ten = floor($number / 10);
        $unit = $number % 10;
        return $tens[$ten] . ($unit > 0 ? ' ' . $units[$unit] : '');
    } elseif ($number < 1000) {
        $hundred = floor($number / 100);
        $remainder = $number % 100;
        return $units[$hundred] . ' trăm' . ($remainder > 0 ? ' ' . convertNumberToWords($remainder) : '');
    } else {
        $groups = [];
        $groupIndex = 0;
        
        while ($number > 0) {
            $group = $number % 1000;
            if ($group > 0) {
                $groupText = convertNumberToWords($group);
                if ($groupIndex > 0) {
                    $groupText .= ' ' . $thousands[$groupIndex];
                }
                array_unshift($groups, $groupText);
            }
            $number = floor($number / 1000);
            $groupIndex++;
        }
        
        return implode(' ', $groups);
    }
}
@endphp