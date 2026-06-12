<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $printable['title'] }} - {{ $printable['code'] }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        .print-page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 12mm;
            background: #fff;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.12);
        }

        .document-meta {
            text-align: center;
            margin-bottom: 12px;
        }

        .document-meta div {
            margin-bottom: 2px;
        }

        .document-title {
            margin: 14px 0 18px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .customer-grid {
            display: grid;
            grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
            gap: 8mm;
            margin-bottom: 16px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr);
            gap: 4px;
            margin-bottom: 4px;
        }

        .info-label,
        .summary-label {
            font-weight: 700;
        }

        .info-value {
            min-width: 0;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px 4px;
            vertical-align: middle;
        }

        th {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
        }

        td {
            overflow-wrap: anywhere;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .summary {
            width: 80mm;
            margin: 14px 0 0 auto;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .summary-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            padding: 2px 0;
        }

        .summary-row.total {
            margin-top: 4px;
            padding-top: 5px;
            border-top: 1px solid #333;
            font-weight: 700;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25mm;
            margin-top: 26px;
            text-align: center;
            font-weight: 700;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .signature-space {
            height: 30mm;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 14px auto 24px;
        }

        .actions button {
            border: 1px solid #bbb;
            border-radius: 4px;
            padding: 8px 18px;
            background: #fff;
            color: #222;
            cursor: pointer;
            font: inherit;
        }

        .actions .primary {
            border-color: #1d4ed8;
            background: #2563eb;
            color: #fff;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        @media print {
            html,
            body {
                background: #fff;
            }

            .print-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $totals = $printable['totals'];
        $formatQuantity = static function ($value) {
            $number = (float) $value;
            return fmod($number, 1.0) === 0.0
                ? number_format($number, 0, ',', '.')
                : rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
        };
    @endphp

    <main class="print-page">
        <div class="document-meta">
            <div><strong>{{ $printable['code_label'] }}:</strong> {{ $printable['code'] }}</div>
            <div><strong>Ngày tạo:</strong> {{ $printable['created_at'] }}</div>
        </div>

        <h1 class="document-title">{{ $printable['title'] }}</h1>

        <section class="customer-grid">
            <div>
                <div class="info-row">
                    <span class="info-label">Khách hàng:</span>
                    <span class="info-value">{{ $printable['customer']['name'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ:</span>
                    <span class="info-value">{{ $printable['customer']['address'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ghi chú:</span>
                    <span class="info-value">{{ $printable['note'] }}</span>
                </div>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Điện thoại:</span>
                    <span class="info-value">{{ $printable['customer']['phone'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $printable['customer']['email'] }}</span>
                </div>
            </div>
        </section>

        <table>
            <colgroup>
                <col style="width: 5%">
                <col style="width: 11%">
                <col style="width: 27%">
                <col style="width: 8%">
                <col style="width: 9%">
                <col style="width: 13%">
                <col style="width: 12%">
                <col style="width: 15%">
            </colgroup>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th>Đơn vị</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Chiết khấu</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                @foreach($printable['items'] as $index => $item)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="center">{{ $item['unit'] }}</td>
                        <td class="number">{{ $formatQuantity($item['quantity']) }}</td>
                        <td class="number">{{ format_vnd($item['price']) }}</td>
                        <td class="number">
                            {{ $item['discount'] > 0 ? format_vnd($item['discount']) : '0%' }}
                        </td>
                        <td class="number">{{ format_vnd($item['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="summary">
            <div class="summary-row">
                <span class="summary-label">Tổng số lượng:</span>
                <span>{{ $formatQuantity($totals['total_quantity']) }}</span>
            </div>
            @if($totals['discount_total'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Chiết khấu:</span>
                    <span>-{{ format_vnd($totals['discount_total']) }}</span>
                </div>
            @endif
            @if($totals['other_fees'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Thu khác:</span>
                    <span>{{ format_vnd($totals['other_fees']) }}</span>
                </div>
            @endif
            @if($totals['delivery_fee'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Phí giao hàng:</span>
                    <span>{{ format_vnd($totals['delivery_fee']) }}</span>
                </div>
            @endif
            <div class="summary-row">
                <span class="summary-label">Tổng tiền:</span>
                <span>{{ format_vnd($totals['total']) }}</span>
            </div>
            <div class="summary-row total">
                <span>Khách phải trả:</span>
                <span>{{ format_vnd($totals['customer_must_pay']) }}</span>
            </div>
            @if($totals['deposit'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Khách đã đặt cọc:</span>
                    <span>{{ format_vnd($totals['deposit']) }}</span>
                </div>
            @endif
            @if($totals['paid'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Khách đã trả:</span>
                    <span>{{ format_vnd($totals['paid']) }}</span>
                </div>
            @endif
            @if($totals['remaining'] > 0)
                <div class="summary-row">
                    <span class="summary-label">Còn phải trả:</span>
                    <span>{{ format_vnd($totals['remaining']) }}</span>
                </div>
            @endif
        </section>

        <section class="signatures">
            <div>
                <div>{{ $printable['signatures']['left_label'] }}</div>
                <div class="signature-space"></div>
            </div>
            <div>
                <div>{{ $printable['signatures']['right_label'] }}</div>
                <div class="signature-space"></div>
            </div>
        </section>
    </main>

    <div class="actions no-print">
        <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.close()">Quay lại</button>
        <button type="button" class="primary" onclick="window.print()">In</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            if (!new URLSearchParams(window.location.search).has('preview')) {
                window.print();
            }
        });
    </script>
</body>
</html>
