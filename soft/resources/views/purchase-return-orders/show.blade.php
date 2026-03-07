@extends('layouts.master')

@section('title', 'Chi tiết đơn trả hàng')

@section('content')
<div class="container-fluid">
    <div id="purchase-return-order-detail-app" data-order-id="{{ $id }}">
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
@vite(['resources/js/purchase-return-order-detail-app.js'])
@endpush