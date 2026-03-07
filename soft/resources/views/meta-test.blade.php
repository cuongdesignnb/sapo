@extends('layouts.master')

@section('title', 'Meta Tags Test')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Meta Tags Test Page</h1>
    
    <div class="bg-gray-100 p-4 rounded">
        <h3 class="font-bold">Server-side Values:</h3>
        <p>CSRF Token: <code>{{ csrf_token() }}</code></p>
        @auth
            <p>API Token: <code>{{ session('api_token') }}</code></p>
            <p>User ID: <code>{{ auth()->user()->id }}</code></p>
        @else
            <p style="color: red;">USER NOT AUTHENTICATED</p>
        @endauth
    </div>

    <div class="bg-blue-100 p-4 rounded mt-4">
        <h3 class="font-bold">Client-side Values:</h3>
        <p>CSRF Token: <span id="csrf-client">Loading...</span></p>
        <p>API Token: <span id="api-client">Loading...</span></p>
        <p>User ID: <span id="user-client">Loading...</span></p>
    </div>

    <div class="mt-4">
        <button onclick="testAPI()" class="bg-blue-500 text-white px-4 py-2 rounded">
            Test API Call
        </button>
        <div id="api-result" class="mt-2"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔍 Meta Tags Test Page loaded');
        
        // Get meta tag values
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
        const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
        
        // Display values
        document.getElementById('csrf-client').textContent = csrfToken || 'MISSING';
        document.getElementById('api-client').textContent = apiToken || 'MISSING';
        document.getElementById('user-client').textContent = userId || 'MISSING';
        
        console.log('🎯 Meta tag values:');
        console.log('CSRF:', csrfToken);
        console.log('API Token:', apiToken);
        console.log('User ID:', userId);
    });

    async function testAPI() {
        const resultDiv = document.getElementById('api-result');
        resultDiv.innerHTML = 'Testing...';
        
        try {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
            
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }
            
            if (apiToken) {
                headers['Authorization'] = `Bearer ${apiToken}`;
            }
            
            console.log('🚀 Making API call with headers:', headers);
            
            const response = await fetch('/api/order-returns', {
                method: 'GET',
                headers: headers
            });
            
            const data = await response.text();
            
            resultDiv.innerHTML = `
                <div class="bg-${response.ok ? 'green' : 'red'}-100 p-2 rounded">
                    <p><strong>Status:</strong> ${response.status}</p>
                    <p><strong>Response:</strong> <pre>${data}</pre></p>
                </div>
            `;
            
            console.log('📥 API Response:', response.status, data);
            
        } catch (error) {
            console.error('❌ API Error:', error);
            resultDiv.innerHTML = `<div class="bg-red-100 p-2 rounded">Error: ${error.message}</div>`;
        }
    }
</script>
@endsection