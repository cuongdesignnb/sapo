<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS System')</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Vite CSS -->
    @vite(['resources/css/app.css'])
    
    <!-- Custom POS Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
        }
        
        .pos-container {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .pos-header {
            background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 100;
        }
        
        .pos-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .pos-header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .pos-header-info {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .pos-content {
            flex: 1;
            overflow: auto;
        }
        
        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Fullscreen styles */
        #pos-app {
            width: 100%;
            height: auto;
            min-height: calc(100vh - 60px);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="pos-container">
        <!-- POS Header -->
        <header class="pos-header">
            <div class="flex items-center gap-4">
                <h1>🏪 Bán hàng POS</h1>
                <div class="pos-header-info">
                    <span id="current-time"></span>
                </div>
            </div>
            
            <div class="pos-header-actions">
                <div class="pos-header-info">
                    <span>{{ auth()->user()->name }}</span> | 
                    <span>{{ $defaultWarehouse->name ?? 'Cửa hàng' }}</span>
                </div>
                
                <a href="{{ route('dashboard') }}" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Dashboard
                </a>
            </div>
        </header>
        
        <!-- POS Content -->
        <main class="pos-content">
            @yield('content')
        </main>
    </div>
    
    <!-- Vite Scripts -->
    @vite(['resources/js/pos-app.js'])
    
    <!-- Global POS Config -->
    <script>
        // Global POS config
        window.posConfig = {
            warehouses: @json($warehouses),
            defaultWarehouse: @json($defaultWarehouse),
            apiToken: '{{ auth()->user()->createToken("pos-access")->plainTextToken }}',
            csrfToken: '{{ csrf_token() }}',
            user: {
                id: {{ auth()->id() }},
                name: '{{ auth()->user()->name }}',
                email: '{{ auth()->user()->email }}'
            }
        };
        
        // Update time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('vi-VN');
            
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = `${dateString} ${timeString}`;
            }
        }
        
        // Initialize time
        updateTime();
        setInterval(updateTime, 1000);
        
        // Prevent page refresh/close accidentally
        window.addEventListener('beforeunload', function(e) {
            // Only show warning if there are unsaved orders
            if (window.posHasUnsavedOrders) {
                e.preventDefault();
                e.returnValue = 'Bạn có đơn hàng chưa lưu. Bạn có chắc muốn thoát?';
                return e.returnValue;
            }
        });
        
        // Fullscreen toggle (F11)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F11') {
                e.preventDefault();
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else {
                    document.documentElement.requestFullscreen();
                }
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>