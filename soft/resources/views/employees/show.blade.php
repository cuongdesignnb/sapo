@extends('layouts.master')

@section('title', 'Chi tiết nhân viên')

@section('content')
<div id="employee-detail-app" data-employee-id="{{ $employeeId }}"></div>
@vite(['resources/js/employee-detail-app.js'])
@endsection
