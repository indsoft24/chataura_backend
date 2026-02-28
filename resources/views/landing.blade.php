<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; color: #1e293b; line-height: 1.6; }
        .container { max-width: 560px; text-align: center; }
        h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: #64748b; margin-bottom: 1.5rem; }
        .links { display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; }
        .links a { color: #3b82f6; text-decoration: none; font-size: 0.9375rem; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ config('app.name') }}</h1>
        <p>Welcome. Use our app for voice and video calls, and more.</p>
        <div class="links">
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            <a href="{{ route('terms-and-conditions') }}">Terms &amp; Conditions</a>
            <a href="{{ route('delete-account') }}">Delete Account</a>
        </div>
    </div>
</body>
</html>
