<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Child Safety Standards – {{ config('app.name') }}</title>
    <meta name="description" content="Chat Aura Child Safety Standards – zero-tolerance policy against child sexual abuse and exploitation. Reporting, moderation, and legal compliance.">
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
        .intro { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <a href="{{ route('home') }}" class="back">← Back to home</a>

    <h1>Child Safety Standards – Chat Aura</h1>
    <p class="updated">Effective date: 24 February 2026</p>

    <p class="intro">Chat Aura is committed to preventing child sexual abuse and exploitation (CSAE) and maintaining a safe environment for all users. We enforce a zero-tolerance policy against child sexual abuse material (CSAM), exploitation, grooming, or any behavior that endangers minors.</p>

    <h2>1. Prohibited Content</h2>
    <p>The following are strictly prohibited on Chat Aura:</p>
    <ul>
        <li>Child sexual abuse material (CSAM)</li>
        <li>Sexual exploitation of minors</li>
        <li>Grooming or predatory behavior</li>
        <li>Solicitation involving minors</li>
        <li>Any illegal or exploitative activity involving individuals under 18</li>
    </ul>
    <p>Any violation results in immediate account suspension and permanent ban.</p>

    <h2>2. Reporting Mechanism</h2>
    <p>Chat Aura provides an in-app reporting system that allows users to report:</p>
    <ul>
        <li>Inappropriate messages</li>
        <li>Suspicious behavior</li>
        <li>Exploitation concerns</li>
        <li>Underage users</li>
    </ul>
    <p>Reports are reviewed by our moderation team promptly.</p>
    <p>Users can also contact us directly at: <a href="mailto:chataura@gmail.com">chataura@gmail.com</a></p>

    <h2>3. Moderation and Prevention Measures</h2>
    <p>To prevent CSAE and harmful behavior, Chat Aura implements:</p>
    <ul>
        <li>User reporting tools</li>
        <li>Account suspension and permanent banning</li>
        <li>Manual moderation review</li>
        <li>Activity monitoring for suspicious patterns</li>
        <li>Compliance with legal requests from authorities</li>
    </ul>

    <h2>4. Legal Compliance</h2>
    <p>Chat Aura complies with:</p>
    <ul>
        <li>Applicable child protection laws</li>
        <li>Regional and national reporting requirements</li>
        <li>Requests from law enforcement agencies</li>
    </ul>
    <p>Where required, we report confirmed child sexual abuse material to relevant authorities.</p>

    <h2>5. Age Restrictions</h2>
    <p>Chat Aura is intended for users aged 13+ (or minimum legal age in their country). We do not knowingly allow users under the legal age to use our platform. If we become aware of an underage user, the account will be removed immediately.</p>

    <h2>6. Contact Information</h2>
    <p>For child safety concerns, please contact:</p>
    <p><strong>Child Safety Contact:</strong> <a href="mailto:chataura05@gmail.com">chataura05@gmail.com</a></p>
    <p><strong>Developer:</strong> Chat Aura<br>
    <strong>Country of Operation:</strong> India</p>
    <p>Chat Aura remains committed to providing a safe and responsible social platform.</p>

    <p><a href="{{ route('home') }}">Return to home</a></p>
</body>
</html>
