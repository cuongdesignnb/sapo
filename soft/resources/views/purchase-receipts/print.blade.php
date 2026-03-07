@extends('layouts.print')

@section('title', 'In Phiếu nhập kho')

@section('content')
<div class="print-container" style="font-family: Arial, sans-serif; font-size: 13px; color:#000;">
    <h2 style="text-align:center; margin-bottom:20px;">PHIẾU NHẬP KHO</h2>
    <table width="100%" style="margin-bottom:15px;">
        <tr>
            <td><strong>Mã phiếu:</strong> {{ $receipt->code }}</td>
            <td><strong>Ngày nhập:</strong> {{ $receipt->received_at }}</td>
        </tr>
        <tr>
            <td><strong>Nhà cung cấp:</strong> {{ $receipt->supplier->name ?? $receipt->purchaseOrder->supplier->name ?? '' }}</td>
            <td><strong>Kho:</strong> {{ $receipt->warehouse->name }}</td>
        </tr>
        <tr>
            <td><strong>Người nhập:</strong> {{ $receipt->receiver->name ?? '' }}</td>
            <td><strong>Trạng thái:</strong> {{ strtoupper($receipt->status) }}</td>
        </tr>
    </table>

    <table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;">
        <thead>
            <tr style="background:#f0f0f0;">
                <th style="width:5%">#</th>
                <th style="width:30%">Sản phẩm</th>
                <th style="width:10%">SL</th>
                <th style="width:15%">Đơn giá</th>
                <th style="width:15%">Thành tiền</th>
                <th style="width:10%">Tình trạng</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->items as $index => $item)
                <tr>
                    <td style="text-align:center;">{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? '' }}<br><span style="color:#555; font-size:11px;">SKU: {{ $item->product->sku ?? '' }}</span></td>
                    <td style="text-align:center;">{{ $item->quantity_received }}</td>
                    <td style="text-align:right;">{{ number_format($item->unit_cost,0,',','.') }}</td>
                    <td style="text-align:right;">{{ number_format($item->total_cost,0,',','.') }}</td>
                    <td style="text-align:center;">{{ $item->condition_status }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right; font-weight:bold;">Tổng cộng</td>
                <td style="text-align:right; font-weight:bold;">{{ number_format($receipt->total_amount,0,',','.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:30px; display:flex; justify-content:space-between;">
        <div style="text-align:center; width:30%;">
            <strong>Người lập phiếu</strong><br><br><br><br>
            <em>(Ký, ghi rõ họ tên)</em>
        </div>
        <div style="text-align:center; width:30%;">
            <strong>Thủ kho</strong><br><br><br><br>
            <em>(Ký, ghi rõ họ tên)</em>
        </div>
        <div style="text-align:center; width:30%;">
            <strong>Kế toán</strong><br><br><br><br>
            <em>(Ký, ghi rõ họ tên)</em>
        </div>
    </div>
</div>
<script>
    window.addEventListener('load', () => window.print());
</script>
@endsection
