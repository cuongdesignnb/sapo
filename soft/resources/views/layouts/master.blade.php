<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @auth
        <meta name="api-token" content="{{ session('api_token') }}">
        <meta name="user-id" content="{{ auth()->user()->id }}">
        <meta name="user-permissions" content="{{ json_encode(auth()->user()->role->permissions ?? []) }}">
        <meta name="current-warehouse" content="{{ session('current_warehouse_id') }}">
    @endauth
    
    <title>@yield('title', 'Quản Lý Bán Hàng - Laravel 11')</title>
    
    {{-- Preload important assets --}}
    <link rel="preload" href="/logo.png" as="image">
    
    {{-- Vite CSS --}}
    @vite(['resources/css/app.css'])
    {{-- Load main app JS only for authenticated pages to avoid mounting errors on guest pages --}}
    @auth
        @vite(['resources/js/app.js'])
    @endauth
    {{-- Warehouse Selector for Super Admin / Admin --}}
    @if(Auth::check() && in_array(Auth::user()->role->name, ['super_admin', 'admin']))
        @vite(['resources/js/warehouse-selector-app.js'])
    @endif
    
    {{-- Custom styles stack --}}
    @stack('styles')
    
    <style>
        /* Custom animations */
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease-out; }
        .animate-pulse-custom { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        
        /* Backdrop blur support */
        .backdrop-blur-custom {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Nav item hover */
        .nav-item {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-item:hover {
            transform: translateX(4px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen font-sans antialiased">
    @guest
        {{-- Login page content --}}
        @yield('content')
    @else
        {{-- Mobile overlay for sidebar --}}
        <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden transition-opacity duration-300"></div>
        
        {{-- Sidebar --}}
        <aside id="sidebar" class="fixed top-0 left-0 z-50 w-72 h-full bg-white shadow-2xl transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out custom-scrollbar overflow-y-auto flex flex-col">
            {{-- Logo section --}}
            <div class="flex items-center justify-between p-6 border-b border-slate-200 bg-gradient-to-r from-blue-600 to-indigo-700">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center ring-2 ring-white/30">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full animate-pulse-custom"></div>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-lg tracking-tight">SalesPro</h1>
                        <p class="text-blue-100 text-xs">Quản lý bán hàng</p>
                    </div>
                </div>
                <button id="close-sidebar" class="lg:hidden text-white hover:bg-white/10 p-2 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            {{-- Navigation --}}
            <nav class="p-4 space-y-2 flex-1">
                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        </svg>
                    </div>
                    <span class="font-medium">Dashboard</span>
                    <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </a>
                
                {{-- Sản phẩm & Cơ sở --}}
                @if(auth()->user()->hasPermission('products.view'))
                <div class="space-y-1">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Sản phẩm & Cơ sở</div>
                    
                    <a href="{{ route('products.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:shadow-sm text-slate-700 hover:text-emerald-600">
                        <div class="p-1.5 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 transition-colors">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Sản phẩm</span>
                        <div class="ml-auto w-2 h-2 bg-emerald-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('categories.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:shadow-sm text-slate-700 hover:text-emerald-600">
                        <div class="p-1.5 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 transition-colors">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Danh mục</span>
                        <div class="ml-auto w-2 h-2 bg-emerald-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('units.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:shadow-sm text-slate-700 hover:text-emerald-600">
                        <div class="p-1.5 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 transition-colors">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3-6.5A1.5 1.5 0 0113.5 6h3A1.5 1.5 0 0118 7.5v1.75a.75.75 0 01-.75.75H16.5a1.5 1.5 0 00-1.5 1.5v.5a1.5 1.5 0 001.5 1.5H18"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Đơn vị tính</span>
                        <div class="ml-auto w-2 h-2 bg-emerald-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
                @endif
                
                {{-- Suppliers --}}
                @if(auth()->user()->hasPermission('suppliers.view'))
                <div class="space-y-1">
                    <a href="{{ route('suppliers.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:shadow-sm text-slate-700 hover:text-emerald-600">
                        <div class="p-1.5 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 transition-colors">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M10.5 3L12 2l1.5 1M21 3H3l2 8h14l2-8z"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Nhà cung cấp</span>
                        <div class="ml-auto w-2 h-2 bg-emerald-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                </div>
                @endif
                {{-- Mua hàng --}}
@if(auth()->user()->hasPermission('suppliers.view'))
<div class="space-y-1">
    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Nhập hàng</div>
    
    <a href="{{ route('purchase-orders.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50 hover:to-purple-50 hover:shadow-sm text-slate-700 hover:text-indigo-600">
        <div class="p-1.5 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 transition-colors">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <span class="font-medium">Đơn nhập hàng</span>
        <div class="ml-auto w-2 h-2 bg-indigo-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    
    <a href="{{ route('purchase-receipts.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50 hover:to-purple-50 hover:shadow-sm text-slate-700 hover:text-indigo-600">
        <div class="p-1.5 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 transition-colors">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
        </div>
        <span class="font-medium">Phiếu nhập hàng</span>
        <div class="ml-auto w-2 h-2 bg-indigo-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    <a href="{{ route('purchase-return-orders.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50 hover:to-purple-50 hover:shadow-sm text-slate-700 hover:text-indigo-600">
    <div class="p-1.5 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 transition-colors">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m5 14-5-2 5-2z"></path>
        </svg>
    </div>
    <span class="font-medium">Đơn trả hàng</span>
    <div class="ml-auto w-2 h-2 bg-indigo-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
</a>

<a href="{{ route('purchase-return-receipts.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50 hover:to-purple-50 hover:shadow-sm text-slate-700 hover:text-indigo-600">
    <div class="p-1.5 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 transition-colors">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
        </svg>
    </div>
    <span class="font-medium">Phiếu trả hàng</span>
    <div class="ml-auto w-2 h-2 bg-indigo-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
</a>
</div>
@endif
{{-- Bán hàng --}}
@if(auth()->user()->hasPermission('orders.view') || auth()->user()->hasPermission('pos.use'))
<div class="space-y-1">
    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Bán hàng</div>
    
    @if(auth()->user()->hasPermission('pos.use'))
    <a href="{{ route('pos.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-green-50 hover:to-emerald-50 hover:shadow-sm text-slate-700 hover:text-green-600">
        <div class="p-1.5 rounded-lg bg-green-100 group-hover:bg-green-200 transition-colors">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.8 9H6m1 0h10m-11 0h11m-10-2a1 1 0 100 2 1 1 0 000-2zM19 10a1 1 0 100 2 1 1 0 000-2z"></path>
            </svg>
        </div>
        <span class="font-medium">POS - Bán hàng</span>
        <div class="ml-auto w-2 h-2 bg-green-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    @endif
    
    @if(auth()->user()->hasPermission('orders.view'))
    <a href="{{ route('orders.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-green-50 hover:to-emerald-50 hover:shadow-sm text-slate-700 hover:text-green-600">
        <div class="p-1.5 rounded-lg bg-green-100 group-hover:bg-green-200 transition-colors">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <span class="font-medium">Đơn hàng</span>
        <div class="ml-auto w-2 h-2 bg-green-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    
    <a href="{{ route('order-returns.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-green-50 hover:to-emerald-50 hover:shadow-sm text-slate-700 hover:text-green-600">
        <div class="p-1.5 rounded-lg bg-green-100 group-hover:bg-green-200 transition-colors">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m5 14-5-2 5-2z"></path>
            </svg>
        </div>
        <span class="font-medium">Khách hàng trả hàng</span>
        <div class="ml-auto w-2 h-2 bg-green-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    @endif
</div>
@endif
{{-- Vận chuyển --}}
@if(auth()->user()->hasPermission('orders.view'))
<div class="space-y-1">
    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Vận chuyển</div>
    
    <a href="{{ route('shipping.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-cyan-50 hover:to-blue-50 hover:shadow-sm text-slate-700 hover:text-cyan-600">
        <div class="p-1.5 rounded-lg bg-cyan-100 group-hover:bg-cyan-200 transition-colors">
            <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
        </div>
        <span class="font-medium">Quản lý vận chuyển</span>
        <div class="ml-auto w-2 h-2 bg-cyan-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    
    @if(auth()->user()->hasPermission('warehouse.manage'))
    <a href="{{ route('shipping.providers') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-cyan-50 hover:to-blue-50 hover:shadow-sm text-slate-700 hover:text-cyan-600">
        <div class="p-1.5 rounded-lg bg-cyan-100 group-hover:bg-cyan-200 transition-colors">
            <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M10.5 3L12 2l1.5 1M21 3H3l2 8h14l2-8z"></path>
            </svg>
        </div>
        <span class="font-medium">Đơn vị vận chuyển</span>
        <div class="ml-auto w-2 h-2 bg-cyan-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </a>
    @endif
</div>
@endif

                {{-- Khách hàng --}}
                @if(auth()->user()->hasPermission('customers.view'))
                <div class="space-y-1">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Khách hàng</div>
                    
                    <a href="{{ route('customers.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 hover:shadow-sm text-slate-700 hover:text-purple-600">
                        <div class="p-1.5 rounded-lg bg-purple-100 group-hover:bg-purple-200 transition-colors">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Khách hàng</span>
                        <div class="ml-auto w-2 h-2 bg-purple-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('customer-groups.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 hover:shadow-sm text-slate-700 hover:text-purple-600">
                        <div class="p-1.5 rounded-lg bg-purple-100 group-hover:bg-purple-200 transition-colors">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Nhóm khách hàng</span>
                        <div class="ml-auto w-2 h-2 bg-purple-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('customer-debts.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 hover:shadow-sm text-slate-700 hover:text-purple-600">
                        <div class="p-1.5 rounded-lg bg-purple-100 group-hover:bg-purple-200 transition-colors">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3-6.5A1.5 1.5 0 0113.5 6h3A1.5 1.5 0 0118 7.5v1.75a.75.75 0 01-.75.75H16.5a1.5 1.5 0 00-1.5 1.5v.5a1.5 1.5 0 001.5 1.5H18m-7-7h.01M12 20h.01"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Công nợ khách hàng</span>
                        <div class="ml-auto w-2 h-2 bg-purple-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
                @endif
                
                {{-- Nhân viên --}}
                @if(auth()->user()->hasPermission('staff.view'))
                <div class="space-y-1">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Nhân viên</div>

                    <a href="{{ route('employees.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="font-medium">Danh sách nhân viên</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <a href="{{ route('employees.schedules') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="font-medium">Lịch làm việc</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <a href="{{ route('employees.attendance') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="font-medium">Bảng chấm công</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <a href="{{ route('employees.payroll') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m2-4h2m-2 0h-2m2 0v2m0-2V9m-4 3h.01" />
                            </svg>
                        </div>
                        <span class="font-medium">Bảng lương</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <a href="{{ route('employees.commissions') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="font-medium">Bảng hoa hồng</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <a href="{{ route('employees.settings') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-sm text-slate-700 hover:text-blue-600">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.983 13.946a1.5 1.5 0 102.034 2.034 1.5 1.5 0 00-2.034-2.034zM12 8.5V6m0 2.5a6.5 6.5 0 106.5 6.5A6.5 6.5 0 0012 8.5z" />
                            </svg>
                        </div>
                        <span class="font-medium">Thiết lập nhân viên</span>
                        <div class="ml-auto w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
                @endif

                {{-- Kho hàng --}}
                @if(auth()->user()->hasPermission('warehouse.view'))
                <div class="space-y-1">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Kho hàng</div>
                    
                    <a href="{{ route('warehouses.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-orange-50 hover:to-red-50 hover:shadow-sm text-slate-700 hover:text-orange-600">
                        <div class="p-1.5 rounded-lg bg-orange-100 group-hover:bg-orange-200 transition-colors">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Kho hàng</span>
                        <div class="ml-auto w-2 h-2 bg-orange-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
                @endif
                {{-- Sổ quỹ --}}
                @if(auth()->user()->hasPermission('suppliers.view'))
                <div class="space-y-1">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2 mt-4">Sổ quỹ</div>
                    
                    <a href="{{ route('cash-receipts.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 hover:shadow-sm text-slate-700 hover:text-teal-600">
                        <div class="p-1.5 rounded-lg bg-teal-100 group-hover:bg-teal-200 transition-colors">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h2m6 1a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Phiếu thu</span>
                        <div class="ml-auto w-2 h-2 bg-teal-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('cash-payments.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-pink-50 hover:to-rose-50 hover:shadow-sm text-slate-700 hover:text-pink-600">
                        <div class="p-1.5 rounded-lg bg-pink-100 group-hover:bg-pink-200 transition-colors">
                            <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Phiếu chi</span>
                        <div class="ml-auto w-2 h-2 bg-pink-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    
                    <a href="{{ route('cash-ledger.index') }}" class="nav-item group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-gradient-to-r hover:from-amber-50 hover:to-yellow-50 hover:shadow-sm text-slate-700 hover:text-amber-600">
                        <div class="p-1.5 rounded-lg bg-amber-100 group-hover:bg-amber-200 transition-colors">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <span class="font-medium">Sổ quỹ</span>
                        <div class="ml-auto w-2 h-2 bg-amber-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
                @endif
            </nav>
            
            {{-- User info at bottom --}}
            <div class="mt-auto p-4 border-t border-slate-200 bg-white">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gradient-to-r from-slate-50 to-gray-50">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->role->display_name ?? 'User' }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition-colors" title="Đăng xuất">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                        </button>
                    </form>
                </div>
                
                {{-- Warehouse Selector --}}
                @if(session('current_warehouse_id'))
                    @php
                        $currentWarehouse = \App\Models\Warehouse::find(session('current_warehouse_id'));
                    @endphp
                    @if($currentWarehouse)
                        <div class="mt-3 p-2 bg-blue-50 rounded-lg">
                            <div class="text-xs text-blue-600 font-medium">Kho hiện tại:</div>
                            <div class="text-sm text-blue-800">{{ $currentWarehouse->name }}</div>
                        </div>
                    @endif
                @endif
            </div>
        </aside>
        
        {{-- Main content area --}}
        <div class="lg:ml-72 min-h-screen">
            {{-- Top header --}}
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-custom border-b border-slate-200/60 shadow-sm">
                <div class="flex items-center justify-between px-6 py-4">
                    {{-- Mobile menu button --}}
                    <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg bg-slate-100 hover:bg-slate-200 transition-colors">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    
                    {{-- Page title --}}
                    <div class="flex-1 lg:flex-none">
                        <h1 class="text-xl lg:text-2xl font-bold gradient-text animate-fade-in-up">
                            @yield('page-title', 'Dashboard')
                        </h1>
                        <p class="text-sm text-slate-500 mt-1">
                            @yield('page-description', 'Trang quản lý hệ thống bán hàng')
                        </p>
                    </div>
                    
                    {{-- Right side actions --}}
                    <div class="flex items-center gap-3">
                        {{-- Warehouse Selector (chỉ cho admin/superadmin) --}}
                        @if(Auth::check() && in_array(Auth::user()->role->name, ['super_admin', 'admin']))
                            <div id="warehouse-selector-mount" 
                                 data-user-role="{{ Auth::user()->role->name }}"
                                 class="hidden sm:block">
                            </div>
                        @endif
                        
                        {{-- User info --}}
                        <div class="hidden sm:flex items-center gap-2 text-sm text-slate-600">
                </div>
            </header>

            {{-- Flash messages --}}
            @if(session('success'))
            <div class="m-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl animate-fade-in-up flex items-center gap-3">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">{{ session('success') }}</div>
                <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-green-600 hover:text-green-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            @endif
            
            @if(session('error'))
            <div class="m-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl animate-fade-in-up flex items-center gap-3">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">{{ session('error') }}</div>
                <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-red-600 hover:text-red-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            @endif

            {{-- Main content --}}
            <main class="p-6 min-h-[calc(100vh-200px)]">
                <div class="animate-fade-in-up">
                    @yield('content')
                </div>
            </main>

            {{-- Footer --}}
            <footer class="bg-white border-t border-slate-200 px-6 py-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4 text-sm text-slate-500">
                        <span>&copy; {{ date('Y') }} SalesPro - Phần mềm quản lý bán hàng</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-slate-500">
                        <span>Laravel {{ app()->version() }}</span>
                        <span>•</span>
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse-custom"></div>
                            <span>Online</span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    @endguest

    {{-- Scripts stack --}}
    @stack('scripts')

    {{-- Core JavaScript --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        
        function toggleSidebar() {
            sidebar?.classList.toggle('-translate-x-full');
            overlay?.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }
        
        mobileMenuBtn?.addEventListener('click', toggleSidebar);
        closeSidebarBtn?.addEventListener('click', toggleSidebar);
        overlay?.addEventListener('click', toggleSidebar);
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && (currentPath === href || currentPath.startsWith(href + '/'))) {
                // Remove existing active classes
                navItems.forEach(nav => {
                    nav.classList.remove('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-lg');
                    nav.classList.add('text-slate-700', 'hover:text-blue-600');
                });
                
                // Add active classes
                item.classList.remove('text-slate-700', 'hover:text-blue-600');
                item.classList.add('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-lg');
                
                // Update icon background
                const iconContainer = item.querySelector('.p-1\\.5');
                if (iconContainer) {
                    iconContainer.classList.remove('bg-blue-100', 'group-hover:bg-blue-200');
                    iconContainer.classList.add('bg-white/20');
                    
                    const icon = iconContainer.querySelector('svg');
                    if (icon) {
                        icon.classList.remove('text-blue-600');
                        icon.classList.add('text-white');
                    }
                }
            }
        });
        
        // Auto-hide mobile menu on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                sidebar?.classList.remove('-translate-x-full');
                overlay?.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
                }
            }
        });
    });

    // Global API helper for Vue components
    window.api = {
        token: document.querySelector('meta[name="api-token"]')?.content || '',
        baseURL: '/api',
        
        async request(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(this.token && { 'Authorization': `Bearer ${this.token}` })
                }
            };

            const response = await fetch(url, { ...defaultOptions, ...options });
            
            // Handle 401 unauthorized
            if (response.status === 401) {
                window.location.href = '/login';
                return;
            }

            return response;
        },

        async get(endpoint) {
            return this.request(`${this.baseURL}${endpoint}`);
        },

        async post(endpoint, data) {
            return this.request(`${this.baseURL}${endpoint}`, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        async put(endpoint, data) {
            return this.request(`${this.baseURL}${endpoint}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        async delete(endpoint) {
            return this.request(`${this.baseURL}${endpoint}`, {
                method: 'DELETE'
            });
        }
    };

    // Global user permissions helper
    window.userPermissions = JSON.parse(document.querySelector('meta[name="user-permissions"]')?.content || '[]');
    window.hasPermission = function(permission) {
        return userPermissions.includes('*') || userPermissions.includes(permission);
    };

    // Global notification helper
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg max-w-sm animate-fade-in-up ${
            type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' :
            type === 'error' ? 'bg-red-50 border border-red-200 text-red-800' :
            type === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' :
            'bg-blue-50 border border-blue-200 text-blue-800'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="flex-1">${message}</div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-current opacity-50 hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    };

    // Global utility functions
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    };

    window.formatDate = function(date, format = 'DD/MM/YYYY') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('DD', day)
            .replace('MM', month)
            .replace('YYYY', year);
    };
    </script>
</body>
</html>