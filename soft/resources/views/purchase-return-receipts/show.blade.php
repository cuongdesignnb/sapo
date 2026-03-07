@extends('layouts.master')

@section('title', 'Chi tiết phiếu trả hàng')

@section('content')
<div class="container-fluid">
    <div id="purchase-return-receipt-detail-app" data-receipt-id="{{ $id }}">
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