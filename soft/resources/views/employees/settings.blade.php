@extends('layouts.master')

@section('title', 'Thiết lập nhân viên')

@section('content')
<div class="container-fluid">
    <div id="employee-settings-app">
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
@vite(['resources/js/employee-settings-app.js'])
@endpush
