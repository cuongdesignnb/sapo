@extends('layouts.master')

@section('title', 'Lịch làm việc')

@section('content')
<div class="container-fluid">
    <div id="employee-schedules-app">
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
@vite(['resources/js/employee-schedules-app.js'])
@endpush
