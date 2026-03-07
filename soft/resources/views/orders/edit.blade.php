@extends('layouts.master')

@section('title', 'Sửa đơn hàng')

@section('content')
<div id="order-edit-app" data-order-id="{{ $orderId }}"></div>
@vite(['resources/js/order-edit-app.js'])
@endsection