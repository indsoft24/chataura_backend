<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy – {{ config('app.name') }}</title>
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

    <h1>Privacy Policy</h1>
    <p class="updated">Last updated: {{ now()->format('F j, Y') }}</p>

    <p>This Privacy Policy describes how {{ config('app.name') }} ("we", "our", or "the app") collects, uses, and shares information when you use our mobile application and related services.</p>

    <h2>Information we collect</h2>
    <p>We may collect:</p>
    <ul>
        <li>Account information (e.g. name, email, phone) that you provide when you register.</li>
        <li>Device information (e.g. device type, OS version) necessary to provide and improve the service.</li>
        <li>Usage data (e.g. call duration, feature usage) to operate and improve the app.</li>
    </ul>

    <h2>How we use your information</h2>
    <p>We use the information to provide, maintain, and improve our services; to process transactions; to send you important notices; and to comply with legal obligations.</p>

    <h2>Sharing of information</h2>
    <p>We do not sell your personal information. We may share information with service providers who assist us in operating the app, or when required by law.</p>

    <h2>Data security</h2>
    <p>We take reasonable measures to protect your data. Communication may be encrypted. No method of transmission over the internet is 100% secure.</p>

    <h2>Your rights</h2>
    <p>Depending on your location, you may have rights to access, correct, or delete your personal data. Contact us to exercise these rights.</p>

    <h2>Children</h2>
    <p>Our services are not directed to children under 13. We do not knowingly collect personal information from children under 13.</p>

    <h2>Changes</h2>
    <p>We may update this Privacy Policy from time to time. We will notify you of material changes via the app or email where appropriate.</p>

    <h2>Contact</h2>
    <p>For privacy-related questions, contact us through the app or at the support email provided in the app listing.</p>

    <p><a href="{{ route('home') }}">Return to home</a></p>
</body>
</html>
