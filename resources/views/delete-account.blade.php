<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Your Account – {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 1.5rem; max-width: 720px; margin-left: auto; margin-right: auto; line-height: 1.65; color: #1e293b; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        h2 { font-size: 1.125rem; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        p, li { margin-bottom: 0.75rem; color: #475569; }
        ol, ul { padding-left: 1.25rem; }
        .back { display: inline-block; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .note { background: #f1f5f9; border-left: 4px solid #3b82f6; padding: 1rem; margin: 1rem 0; border-radius: 0 4px 4px 0; }
        .steps { list-style: decimal; }
        .steps li { margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <a href="{{ route('home') }}" class="back">← Back to home</a>

    <h1>Delete Your Account</h1>

    <p>You can delete your {{ config('app.name') }} account and associated data at any time. Account deletion is handled entirely within the app and our backend. Once deleted, your account and data cannot be recovered.</p>

    <h2>How to delete your account</h2>
    <ol class="steps">
        <li>Open the {{ config('app.name') }} app on your device.</li>
        <li>Go to <strong>Profile</strong> or <strong>Settings</strong> (menu or account icon).</li>
        <li>Find <strong>Account</strong> or <strong>Delete account</strong>.</li>
        <li>Follow the in-app steps and confirm deletion when prompted.</li>
    </ol>
    <p>After you confirm, we process the deletion on our servers. Your account and associated data are removed from our systems.</p>

    <div class="note">
        <strong>Note:</strong> Everything is handled in the app and on our backend. There is no need to contact us by email for standard account deletion—use the option inside the app.
    </div>

    <h2>What gets deleted</h2>
    <p>When you delete your account, we remove your profile, account information, and other data we hold that is associated with your account, in line with our <a href="{{ route('privacy-policy') }}">Privacy Policy</a>.</p>

    <h2>Need help?</h2>
    <p>If you cannot find the delete option in the app or have trouble completing the process, contact us through the in-app support or the contact details provided in the app listing.</p>

    <p><a href="{{ route('home') }}">Return to home</a></p>
</body>
</html>
