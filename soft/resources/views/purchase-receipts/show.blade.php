@extends('layouts.master')

@section('title', 'Chi tiết Phiếu nhập kho')

@section('content')
<div class="container-fluid">
    <!-- Vue App sẽ render ở đây -->
    <div id="purchase-receipt-detail-app" data-id="{{ $id }}">
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
@vite(['resources/js/purchase-receipt-detail-app.js'])
@endpush