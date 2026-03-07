@extends('layouts.pos')

@section('title', 'Bán hàng POS')

@section('content')
<div class="container-fluid">
    <!-- Vue App sẽ render ở đây -->
    <div id="pos-app">
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
@vite(['resources/js/pos-app.js'])
<script>
    // Global POS config
    window.posConfig = {
        warehouses: @json($warehouses),
        defaultWarehouse: @json($defaultWarehouse),
        apiToken: '{{ auth()->user()->createToken("web-access")->plainTextToken }}',
        csrfToken: '{{ csrf_token() }}'
    };
</script>
@endpush