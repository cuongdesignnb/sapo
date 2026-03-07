<!DOCTYPE html>
<html>
<head>
    <title>Debug 2FA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .debug-box { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px; }
        button { padding: 10px 20px; margin: 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a87; }
        .result { margin-top: 20px; padding: 10px; background: #e7f3ff; border-left: 4px solid #007cba; }
    </style>
</head>
<body>
    <h1>Debug 2FA Login Flow</h1>
    
    <div class="debug-box">
        <h2>Test Session</h2>
        <button onclick="testSession()">Check Session</button>
        <button onclick="testManual2FA()">Manual 2FA Setup</button>
        <button onclick="testLoginDirect()">Direct Login Test</button>
    </div>
    
    <div class="debug-box">
        <h2>Quick Login Form</h2>
        <form id="quickLogin" action="/login" method="POST">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="email" name="email" value="admin@banhangpro.com" placeholder="Email" style="padding: 8px; width: 250px;"><br><br>
            <input type="password" name="password" value="123456" placeholder="Password" style="padding: 8px; width: 250px;"><br><br>
            <button type="submit">Login Now</button>
        </form>
    </div>
    
    <div id="result" class="result" style="display: none;"></div>

    <script>
        function showResult(data) {
            const result = document.getElementById('result');
            result.style.display = 'block';
            result.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        }
        
        async function testSession() {
            try {
                const response = await fetch('/test-session');
                const data = await response.json();
                showResult(data);
            } catch (error) {
                showResult({ error: error.message });
            }
        }
        
        async function testManual2FA() {
            try {
                window.location.href = '/test-2fa-manual';
            } catch (error) {
                showResult({ error: error.message });
            }
        }
        
        async function testLoginDirect() {
            try {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('email', 'admin@banhangpro.com');
                formData.append('password', '123456');
                
                const response = await fetch('/login', {
                    method: 'POST',
                    body: formData,
                    redirect: 'manual'
                });
                
                showResult({
                    status: response.status,
                    redirected: response.redirected,
                    url: response.url,
                    location: response.headers.get('location')
                });
            } catch (error) {
                showResult({ error: error.message });
            }
        }
    </script>
</body>
</html>