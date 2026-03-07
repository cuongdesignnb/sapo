@extends('layouts.master')

@section('title', 'Chỉnh sửa đơn trả hàng cho nhà cung cấp')

@section('content')
<div class="container-fluid">
    <div id="purchase-return-order-edit-app" data-id="{{ $id }}">
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
@vite(['resources/js/purchase-return-order-edit-app.js'])
@endpush