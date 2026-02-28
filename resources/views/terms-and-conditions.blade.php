<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms and Conditions – {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 1.5rem; max-width: 720px; margin-left: auto; margin-right: auto; line-height: 1.65; color: #1e293b; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        h2 { font-size: 1.125rem; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        p, li { margin-bottom: 0.75rem; color: #475569; }
        ul { padding-left: 1.25rem; }
        .back { display: inline-block; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .updated { font-size: 0.875rem; color: #94a3b8; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <a href="{{ route('home') }}" class="back">← Back to home</a>

    <h1>Terms and Conditions</h1>
    <p class="updated">Last updated: {{ now()->format('F j, Y') }}</p>

    <p>Please read these Terms and Conditions ("Terms") before using {{ config('app.name') }} ("the app" or "service"). By using the app, you agree to these Terms.</p>

    <h2>Acceptance of terms</h2>
    <p>By downloading, installing, or using the app, you agree to be bound by these Terms and our Privacy Policy. If you do not agree, do not use the app.</p>

    <h2>Eligibility</h2>
    <p>You must be at least 13 years old (or the minimum age in your country) to use the app. By using the app, you represent that you meet this requirement.</p>

    <h2>Use of the service</h2>
    <p>You agree to use the app only for lawful purposes. You must not:</p>
    <ul>
        <li>Violate any applicable laws or regulations.</li>
        <li>Infringe others' intellectual property or privacy.</li>
        <li>Transmit harmful, abusive, or illegal content.</li>
        <li>Attempt to gain unauthorized access to our systems or other users' accounts.</li>
    </ul>

    <h2>Account and security</h2>
    <p>You are responsible for keeping your account credentials secure and for all activity under your account. Notify us promptly of any unauthorized use.</p>

    <h2>Virtual items and coins</h2>
    <p>Virtual coins or items in the app have no real-world value and cannot be exchanged for cash or other consideration outside the app, except where we explicitly allow. We may modify or discontinue virtual economy features with reasonable notice where feasible.</p>

    <h2>Disclaimer of warranties</h2>
    <p>The app is provided "as is" without warranties of any kind. We do not guarantee uninterrupted or error-free service.</p>

    <h2>Limitation of liability</h2>
    <p>To the fullest extent permitted by law, we shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the app.</p>

    <h2>Changes to terms</h2>
    <p>We may update these Terms from time to time. Continued use of the app after changes constitutes acceptance. We will use reasonable means to notify you of material changes.</p>

    <h2>Termination</h2>
    <p>We may suspend or terminate your access to the app for violation of these Terms or for any other reason at our discretion.</p>

    <h2>Contact</h2>
    <p>For questions about these Terms, contact us through the app or at the support email provided in the app listing.</p>

    <p><a href="{{ route('home') }}">Return to home</a></p>
</body>
</html>
