@extends('layouts.master')

@section('title', 'Sổ quỹ')

@section('content')
<div class="container-fluid">
    <!-- Vue App sẽ render ở đây -->
    <div id="cash-ledger-app">
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
@vite(['resources/js/cash-ledger-app.js'])
@endpush