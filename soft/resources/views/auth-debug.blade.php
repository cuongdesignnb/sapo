<!DOCTYPE html>
<html>
<head>
    <title>Auth Debug</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="api-token" content="{{ session('api_token') }}">
        <meta name="user-id" content="{{ auth()->user()->id }}">
    @endauth
</head>
<body>
    <h1>Authentication Debug</h1>
    
    <div>
        <h3>Auth Status:</h3>
        <p>Authenticated: {{ auth()->check() ? 'YES' : 'NO' }}</p>
        @auth
            <p>User ID: {{ auth()->user()->id }}</p>
            <p>User Name: {{ auth()->user()->name }}</p>
            <p>User Email: {{ auth()->user()->email }}</p>
            <p>API Token in Session: {{ session('api_token') ? 'EXISTS' : 'MISSING' }}</p>
            <p>Current Warehouse: {{ session('current_warehouse_id') ?? 'NONE' }}</p>
        @else
            <p style="color: red;">USER NOT AUTHENTICATED</p>
        @endauth
    </div>

    <div>
        <h3>Session Data:</h3>
        <pre>{{ json_encode(session()->all(), JSON_PRETTY_PRINT) }}</pre>
    </div>

    <div>
        <h3>Meta Tags Check:</h3>
        <p>CSRF Token: <code>{{ csrf_token() }}</code></p>
        @auth
            <p>API Token: <code>{{ session('api_token') }}</code></p>
            <p>User ID: <code>{{ auth()->user()->id }}</code></p>
        @else
            <p style="color: red;">USER NOT AUTHENTICATED - No meta tags</p>
        @endauth
    </div>

    <div>
        <h3>Actions:</h3>
        @auth
            <a href="/logout" style="padding: 10px; background: red; color: white; text-decoration: none;">Logout</a>
        @else
            <a href="/login" style="padding: 10px; background: blue; color: white; text-decoration: none;">Login</a>
        @endauth
        <a href="/order-returns" style="padding: 10px; background: green; color: white; text-decoration: none;">Go to Order Returns</a>
    </div>

    <script>
        console.log('Auth Debug Page Loaded');
        console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]')?.content);
        console.log('API Token:', document.querySelector('meta[name="api-token"]')?.content);
        console.log('User ID:', document.querySelector('meta[name="user-id"]')?.content);
    </script>
</body>
</html>