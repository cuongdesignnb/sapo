<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu Thu - {{ $receipt->code }}</title>
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
            border-bottom: 2px solid #000;
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

        .receipt-section {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
            text-align: center;
            padding-left: 15px;
        }

        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .receipt-code {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-date {
            font-size: 10px;
        }

        .receipt-info {
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
            border: 2px solid #000;
            text-align: center;
            background: #f9f9f9;
        }

        .amount-label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .amount-number {
            font-size: 20px;
            font-weight: bold;
            color: #d63384;
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
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
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
                background: #f9f9f9 !important;
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
            background: #007bff;
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
            background: #0056b3;
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
                <div class="company-name">{{ config('company.name', $receipt->warehouse->company_name ?? 'CÔNG TY CỔ PHẦN ABC') }}</div>
                <div class="company-info">
                    <strong>Địa chỉ:</strong> {{ config('company.address', $receipt->warehouse->address ?? 'Địa chỉ công ty') }}
                </div>
                <div class="company-info">
                    <strong>Điện thoại:</strong> {{ config('company.phone', $receipt->warehouse->phone ?? '0123456789') }}
                    @if(config('company.email', $receipt->warehouse->email))
                    | <strong>Email:</strong> {{ config('company.email', $receipt->warehouse->email ?? 'info@company.com') }}
                    @endif
                </div>
                <div class="company-info">
                    @if(config('company.tax_code', $receipt->warehouse->tax_code))
                    <strong>MST:</strong> {{ config('company.tax_code', $receipt->warehouse->tax_code ?? '0123456789') }}
                    @endif
                    @if(config('company.website'))
                    | <strong>Website:</strong> {{ config('company.website') }}
                    @endif
                </div>
            </div>
            
            <div class="receipt-section">
                <div class="receipt-title">Phiếu Thu</div>
                <div class="receipt-code">Số: {{ $receipt->code }}</div>
                <div class="receipt-date">{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</div>
            </div>
        </div>

        <!-- Receipt Information -->
        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">Họ tên người nộp:</span>
                <span class="info-value">{{ $receipt->recipient->name ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Địa chỉ:</span>
                <span class="info-value">{{ $receipt->recipient->address ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Lý do thu:</span>
                <span class="info-value">{{ $receipt->receiptType->name ?? $receipt->reason ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Ngày thu:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Hình thức thanh toán:</span>
                <span class="info-value">
                    @if($receipt->payment_method === 'cash')
                        Tiền mặt
                    @elseif($receipt->payment_method === 'bank_transfer')
                        Chuyển khoản
                    @elseif($receipt->payment_method === 'card')
                        Thẻ tín dụng
                    @else
                        {{ $receipt->payment_method }}
                    @endif
                </span>
            </div>
            
            @if($receipt->reference_number)
            <div class="info-row">
                <span class="info-label">Số tham chiếu:</span>
                <span class="info-value">{{ $receipt->reference_number }}</span>
            </div>
            @endif

            @if($receipt->bank_name || $receipt->bank_account)
            <div class="info-row">
                <span class="info-label">Thông tin ngân hàng:</span>
                <span class="info-value">
                    {{ $receipt->bank_name ?? '' }} 
                    @if($receipt->bank_account)
                        - TK: {{ $receipt->bank_account }}
                    @endif
                </span>
            </div>
            @endif
        </div>

        <!-- Amount Section -->
        <div class="amount-section">
            <div class="amount-label">Số tiền thu được</div>
            <div class="amount-number">{{ number_format($receipt->amount, 0, ',', '.') }} VNĐ</div>
            <div class="amount-words">Bằng chữ: {{ convertNumberToWords($receipt->amount) }} đồng</div>
        </div>

        <!-- Note Section -->
        @if($receipt->note)
        <div class="note-section">
            <div class="info-row">
                <span class="info-label">Ghi chú:</span>
                <span class="info-value">{{ $receipt->note }}</span>
            </div>
        </div>
        @endif

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">Người nộp tiền</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('m') }} năm {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('Y') }}</div>
                <div class="signature-name">{{ $receipt->recipient->name ?? '_________________' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-title">Người thu tiền</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('m') }} năm {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('Y') }}</div>
                <div class="signature-name">{{ $receipt->creator->name ?? $receipt->cashier->name ?? '_________________' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-title">Kế toán trưởng</div>
                <div class="signature-date">Ngày {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d') }} tháng {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('m') }} năm {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('Y') }}</div>
                <div class="signature-name">{{ $receipt->approver->name ?? '_________________' }}</div>
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
                <div>Phiếu thu được tạo bởi {{ config('company.name', 'Hệ thống quản lý') }}</div>
            </div>
            <div class="footer-right">
                <div>In lúc: {{ now()->format('d/m/Y H:i:s') }}</div>
                <div style="margin-top: 5px;">
                    Trạng thái: 
                    <span class="status-badge status-{{ $receipt->status }}">
                        @if($receipt->status === 'approved')
                            ĐÃ DUYỆT
                        @elseif($receipt->status === 'pending')
                            CHỜ DUYỆT
                        @elseif($receipt->status === 'rejected')
                            TỪ CHỐI
                        @else
                            {{ strtoupper($receipt->status) }}
                        @endif
                    </span>
                </div>
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