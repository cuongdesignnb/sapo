@extends('layouts.master')

@section('title', 'Chi tiết phiếu trả hàng - ' . ($receipt->code ?? 'N/A'))

@section('content')
<div class="container-fluid">
    <!-- Vue App sẽ render ở đây -->
    <div id="purchase-return-receipt-detail-app" data-receipt-id="{{ $receipt->id ?? request()->route('id') }}">
        <!-- Loading state khi Vue chưa load -->
        <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
@vite(['resources/css/app.css'])
@endpush

@push('scripts')
@vite(['resources/js/purchase-return-receipt-detail-app.js'])
@endpush