@extends('layouts.master')

@section('title', 'Chi tiết đơn hàng')

@section('content')
<div id="order-detail-app" data-order-id="{{ $orderId }}"></div>
@vite(['resources/js/order-detail-app.js'])
@endsection