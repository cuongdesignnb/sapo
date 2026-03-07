@extends('layouts.master')

@section('title', 'Chỉnh sửa Phiếu nhập kho')

@section('content')
<div class="container-fluid">
    <div id="purchase-receipt-edit-app" data-id="{{ $id }}">
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
@vite(['resources/js/purchase-receipt-edit-app.js'])
@endpush
