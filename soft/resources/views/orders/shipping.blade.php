@extends('layouts.master')

@section('title', 'Tạo đơn giao hàng')

@section('content')
<div class="container-fluid">
    <div id="shipping-form-app">
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
@vite(['resources/js/shipping-form-app.js'])
@endpush