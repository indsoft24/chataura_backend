<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Chat Aura — Social audio & party rooms. Voice and video calls, live rooms, virtual gifts, and real-time chat. Download the Android app.">
    <title>Chat Aura — Voice, Video & Party Rooms</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0a0a0f;
            --bg-card: rgba(18, 18, 28, 0.72);
            --bg-card-hover: rgba(28, 28, 42, 0.88);
            --border-subtle: rgba(255, 255, 255, 0.06);
            --text-primary: #f1f2f6;
            --text-secondary: #9ca3b8;
            --text-muted: #6b7280;
            --accent-cyan: #22d3ee;
            --accent-violet: #a78bfa;
            --accent-rose: #fb7185;
            --glow-cyan: rgba(34, 211, 238, 0.25);
            --glow-violet: rgba(167, 139, 250, 0.2);
            --font-head: 'Outfit', system-ui, sans-serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: var(--font-body);
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-deep);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Aurora gradient background */
        .aurora {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 100% 80% at 50% -20%, var(--glow-violet), transparent 50%),
                radial-gradient(ellipse 80% 60% at 90% 30%, var(--glow-cyan), transparent 45%),
                radial-gradient(ellipse 60% 50% at 10% 70%, rgba(251, 113, 133, 0.12), transparent 45%);
            pointer-events: none;
        }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Header */
        header {
            padding: 1.5rem 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }
        .logo-img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 12px;
        }
        .logo-wordmark {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, var(--text-primary), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        nav a:hover { color: var(--accent-cyan); }

        /* Hero */
        .hero {
            text-align: center;
            padding: 3rem 0 4rem;
        }
        .hero .logo-hero {
            width: 96px;
            height: 96px;
            object-fit: contain;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            box-shadow: 0 0 40px var(--glow-cyan);
        }
        .hero h1 {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: clamp(2.25rem, 5vw, 3.5rem);
            letter-spacing: -0.03em;
            margin: 0 0 0.75rem;
            line-height: 1.15;
        }
        .hero .tagline {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--text-secondary);
            max-width: 480px;
            margin: 0 auto 2rem;
        }
        .hero .sub {
            color: var(--text-muted);
            font-size: 0.9375rem;
        }

        /* Section titles */
        .sec {
            padding: 3.5rem 0;
        }
        .sec-title {
            font-family: var(--font-head);
            font-weight: 600;
            font-size: clamp(1.5rem, 3vw, 1.75rem);
            text-align: center;
            margin: 0 0 0.5rem;
        }
        .sec-desc {
            text-align: center;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 auto 2.5rem;
        }

        /* Feature grid */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
        }
        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.5rem;
            transition: background 0.25s, border-color 0.25s, transform 0.2s;
        }
        .feature-card:hover {
            background: var(--bg-card-hover);
            border-color: rgba(34, 211, 238, 0.15);
            transform: translateY(-2px);
        }
        .feature-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 1rem;
        }
        .feature-card .icon.rooms { background: linear-gradient(135deg, rgba(167, 139, 250, 0.25), rgba(139, 92, 246, 0.15)); }
        .feature-card .icon.calls { background: linear-gradient(135deg, rgba(34, 211, 238, 0.25), rgba(6, 182, 212, 0.15)); }
        .feature-card .icon.gifts { background: linear-gradient(135deg, rgba(251, 113, 133, 0.25), rgba(244, 63, 94, 0.15)); }
        .feature-card .icon.chat { background: linear-gradient(135deg, rgba(52, 211, 153, 0.25), rgba(16, 185, 129, 0.15)); }
        .feature-card .icon.wallet { background: linear-gradient(135deg, rgba(250, 204, 21, 0.2), rgba(234, 179, 8, 0.1)); }
        .feature-card .icon.invite { background: linear-gradient(135deg, rgba(34, 211, 238, 0.2), rgba(167, 139, 250, 0.15)); }
        .feature-card h3 {
            font-family: var(--font-head);
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0 0 0.35rem;
        }
        .feature-card p {
            margin: 0;
            font-size: 0.9375rem;
            color: var(--text-secondary);
        }

        /* What is Chat Aura */
        .about-list {
            max-width: 620px;
            margin: 0 auto;
            list-style: none;
            padding: 0;
        }
        .about-list li {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--border-subtle);
        }
        .about-list li:last-child { border-bottom: none; }
        .about-list .bullet {
            flex-shrink: 0;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-violet));
            margin-top: 0.5rem;
        }
        .about-list strong { color: var(--text-primary); }

        /* CTA */
        .cta {
            text-align: center;
            padding: 3rem 0 4rem;
        }
        .cta p {
            color: var(--text-secondary);
            margin: 0 0 1.25rem;
        }
        .store-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: background 0.2s, border-color 0.2s;
        }
        .store-badge:hover {
            background: var(--bg-card-hover);
            border-color: rgba(34, 211, 238, 0.3);
        }
        .store-badge svg { width: 24px; height: 24px; }

        /* Footer */
        footer {
            padding: 2rem 0 2.5rem;
            border-top: 1px solid var(--border-subtle);
        }
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem 1.5rem;
        }
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9375rem;
        }
        .footer-links a:hover { color: var(--accent-cyan); }
        .footer-copy {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8125rem;
            margin-top: 1.25rem;
        }

        /* Welcome block */
        .welcome {
            max-width: 720px;
            margin: 0 auto 4rem;
            padding: 2rem 1.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            text-align: center;
        }
        .welcome p {
            margin: 0 0 1rem;
            color: var(--text-secondary);
            font-size: 1.0625rem;
            line-height: 1.7;
        }
        .welcome p:last-child { margin-bottom: 0; }

        /* Big feature blocks (Smart Messaging, Audio & Video, etc.) */
        .feature-block {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            align-items: start;
            max-width: 900px;
            margin: 0 auto 3rem;
            padding: 2rem 1.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .feature-block:hover {
            border-color: rgba(34, 211, 238, 0.12);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.24);
        }
        @media (min-width: 640px) {
            .feature-block { grid-template-columns: 56px 1fr; gap: 1.75rem; }
        }
        .feature-block .block-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            flex-shrink: 0;
        }
        .feature-block .block-icon.msg { background: linear-gradient(135deg, rgba(52, 211, 153, 0.28), rgba(16, 185, 129, 0.18)); }
        .feature-block .block-icon.call { background: linear-gradient(135deg, rgba(34, 211, 238, 0.28), rgba(6, 182, 212, 0.18)); }
        .feature-block .block-icon.party { background: linear-gradient(135deg, rgba(167, 139, 250, 0.28), rgba(139, 92, 246, 0.18)); }
        .feature-block .block-icon.gift { background: linear-gradient(135deg, rgba(251, 113, 133, 0.28), rgba(244, 63, 94, 0.18)); }
        .feature-block .block-icon.profile { background: linear-gradient(135deg, rgba(250, 204, 21, 0.22), rgba(234, 179, 8, 0.12)); }
        .feature-block h3 {
            font-family: var(--font-head);
            font-weight: 600;
            font-size: 1.35rem;
            margin: 0 0 0.5rem;
        }
        .feature-block .block-desc {
            color: var(--text-secondary);
            margin: 0 0 1rem;
            font-size: 0.9375rem;
        }
        .feature-block ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .feature-block ul li {
            position: relative;
            padding-left: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }
        .feature-block ul li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent-cyan);
        }

        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border-subtle), transparent);
            margin: 3rem 0;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Download CTA section */
        .download-sec {
            padding: 4rem 1.5rem;
            text-align: center;
            background: linear-gradient(180deg, transparent, rgba(34, 211, 238, 0.06));
            border-radius: 24px;
            border: 1px solid var(--border-subtle);
        }
        .download-sec h2 {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: clamp(1.5rem, 3vw, 2rem);
            margin: 0 0 0.5rem;
        }
        .download-sec .download-desc {
            color: var(--text-secondary);
            margin: 0 0 1.5rem;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }
        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--accent-cyan), #06b6d4);
            color: #0a0a0f;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.0625rem;
            border-radius: 14px;
            border: none;
            box-shadow: 0 4px 24px rgba(34, 211, 238, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(34, 211, 238, 0.45);
        }
        .download-btn svg { width: 28px; height: 28px; flex-shrink: 0; }
        .download-sec .link-alt {
            display: block;
            margin-top: 1rem;
            color: var(--accent-cyan);
            font-size: 0.9375rem;
            text-decoration: none;
        }
        .download-sec .link-alt:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="aurora" aria-hidden="true"></div>

    <div class="wrap">
        <header>
            <a href="{{ route('home') }}" class="logo-wrap">
                <img src="{{ asset('logo.png') }}" alt="" class="logo-img" width="48" height="48">
                <span class="logo-wordmark">Chat Aura</span>
            </a>
            <nav>
                <a href="#download">Download</a>
                <a href="{{ route('privacy-policy') }}">Privacy</a>
                <a href="{{ route('terms-and-conditions') }}">Terms</a>
                <a href="{{ route('child-safety') }}">Child Safety</a>
            </nav>
        </header>

        <section class="hero">
            <img src="{{ asset('logo.png') }}" alt="" class="logo-hero" width="96" height="96">
            <h1>Chat Aura</h1>
            <p class="tagline">Where voice, video &amp; vibes connect. Party rooms, 1‑to‑1 calls, gifts, and real-time chat — all in one place.</p>
            <p class="sub">Social audio &amp; party rooms for Android</p>
        </section>

        <section class="welcome">
            <p>Welcome to Chat Aura — your ultimate social space to connect, communicate, and enjoy real-time interactions through chat, audio calls, video calls, and live party rooms.</p>
            <p>Chat Aura is designed to bring people closer through seamless communication and engaging social experiences. Whether you want to chat privately, join fun party rooms, or interact through live audio and video calls, Chat Aura provides a smooth and secure environment for meaningful connections.</p>
        </section>

        <section class="sec" id="features">
            <h2 class="sec-title">Everything in one app</h2>
            <p class="sec-desc">Chat Aura brings together live rooms, crystal-clear voice and video, virtual gifts, and instant messaging.</p>
            <div class="features">
                <article class="feature-card">
                    <div class="icon rooms">🎙️</div>
                    <h3>Party rooms</h3>
                    <p>Create or join live rooms. Take a seat, unmute, and talk — with optional video. Host controls, themes, and room settings.</p>
                </article>
                <article class="feature-card">
                    <div class="icon calls">📹</div>
                    <h3>Voice &amp; video calls</h3>
                    <p>One-to-one audio and video calls with push notifications when the app is closed. Reliable, low-latency with Agora.</p>
                </article>
                <article class="feature-card">
                    <div class="icon gifts">🎁</div>
                    <h3>Virtual gifts</h3>
                    <p>Send gifts in rooms or in private chat. Coins, wallet, and recharge packages. Support creators and level up your profile.</p>
                </article>
                <article class="feature-card">
                    <div class="icon chat">💬</div>
                    <h3>Real-time chat</h3>
                    <p>Text, emojis, and gifts in private conversations. Message status (sent, delivered, read) and FCM notifications.</p>
                </article>
                <article class="feature-card">
                    <div class="icon wallet">👛</div>
                    <h3>Wallet &amp; packages</h3>
                    <p>Recharge with packages, send gifts, withdraw earnings. Transaction history and referral rewards.</p>
                </article>
                <article class="feature-card">
                    <div class="icon invite">✨</div>
                    <h3>Invite &amp; earn</h3>
                    <p>Share your invite code. Friends sign up, you earn. Lucky spin and room games add extra fun.</p>
                </article>
            </div>
        </section>

        <div class="divider" aria-hidden="true"></div>

        <section class="sec" id="experience">
            <h2 class="sec-title">Designed for connection</h2>
            <p class="sec-desc">Every feature is built to keep you close to the people who matter.</p>

            <div class="feature-block">
                <div class="block-icon msg">💬</div>
                <div>
                    <h3>Smart Messaging</h3>
                    <p class="block-desc">Stay connected with your friends using our fast and simple chat system.</p>
                    <ul>
                        <li>One-to-one private chat</li>
                        <li>Real-time message delivery</li>
                        <li>Send emojis and interactive gifts</li>
                        <li>Instant notifications</li>
                    </ul>
                </div>
            </div>

            <div class="feature-block">
                <div class="block-icon call">📹</div>
                <div>
                    <h3>Audio &amp; Video Calling</h3>
                    <p class="block-desc">Experience high-quality calls anytime.</p>
                    <ul>
                        <li>1-to-1 audio calls</li>
                        <li>1-to-1 video calls</li>
                        <li>Group interaction support</li>
                        <li>Smooth connection and low latency</li>
                    </ul>
                </div>
            </div>

            <div class="feature-block">
                <div class="block-icon party">🎙️</div>
                <div>
                    <h3>Live Party Rooms</h3>
                    <p class="block-desc">Join or host party rooms and socialize in real time.</p>
                    <ul>
                        <li>Multi-user audio &amp; video interaction</li>
                        <li>Live chat inside rooms</li>
                        <li>Gift sharing and reactions</li>
                        <li>Interactive experience with friends</li>
                    </ul>
                </div>
            </div>

            <div class="feature-block">
                <div class="block-icon gift">🎁</div>
                <div>
                    <h3>Gifts &amp; Fun Engagement</h3>
                    <p class="block-desc">Make conversations more exciting.</p>
                    <ul>
                        <li>Send virtual gifts</li>
                        <li>Level-based experience system</li>
                        <li>Profile frames and rewards</li>
                    </ul>
                </div>
            </div>

            <div class="feature-block">
                <div class="block-icon profile">✨</div>
                <div>
                    <h3>Personal Profile &amp; Levels</h3>
                    <p class="block-desc">Show your personality and grow your presence.</p>
                    <ul>
                        <li>Profile customization</li>
                        <li>Level progression</li>
                        <li>Unlock special frames and features</li>
                    </ul>
                </div>
            </div>
        </section>

        <div class="divider" aria-hidden="true"></div>

        <section class="sec" id="about">
            <h2 class="sec-title">What is Chat Aura?</h2>
            <p class="sec-desc">A social audio and party rooms Android app powered by a secure Laravel backend and real-time infrastructure.</p>
            <ul class="about-list">
                <li>
                    <span class="bullet"></span>
                    <span><strong>Live rooms</strong> — Discover hot parties, create your own room with custom seats (1–20), enable video or gifts, and manage members. Agora powers voice and video.</span>
                </li>
                <li>
                    <span class="bullet"></span>
                    <span><strong>1‑to‑1 calls</strong> — Initiate or receive audio/video calls with FCM push so you never miss a call. Token-based auth and call state handled by the backend.</span>
                </li>
                <li>
                    <span class="bullet"></span>
                    <span><strong>Messages &amp; gifts</strong> — Send text, emojis, or gifts in conversations. Wallet deduction, admin commission, and notifications for new messages.</span>
                </li>
                <li>
                    <span class="bullet"></span>
                    <span><strong>Wallet &amp; safety</strong> — Recharge, send gifts, withdraw. Privacy policy, terms, child safety, and delete-account options available on the web.</span>
                </li>
            </ul>
        </section>

        <section class="download-sec" id="download">
            <h2>Download Chat Aura</h2>
            <p class="download-desc">Get the app on your Android device and start connecting with friends through chat, calls, and live party rooms.</p>
            @php $downloadUrl = config('app.app_download_url'); @endphp
            @if($downloadUrl)
                <a href="{{ $downloadUrl }}" class="download-btn" target="_blank" rel="noopener noreferrer" aria-label="Download Chat Aura on Google Play">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.674 6.287L3.61 22.186l10.89-9.479zM5.864 2.658L16.802 8.99l-2.302 2.302-8.636-8.634zm12.712 10.994l2.387 2.386a1 1 0 01-.047 1.454l-1.54 1.267-2.387-2.386 1.54-1.267a1 1 0 011.047-.054z"/></svg>
                    Download on Google Play
                </a>
            @else
                <a href="#" class="download-btn" aria-disabled="true" style="opacity: 0.85; cursor: default;">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.674 6.287L3.61 22.186l10.89-9.479zM5.864 2.658L16.802 8.99l-2.302 2.302-8.636-8.634zm12.712 10.994l2.387 2.386a1 1 0 01-.047 1.454l-1.54 1.267-2.387-2.386 1.54-1.267a1 1 0 011.047-.054z"/></svg>
                    Coming to Google Play soon
                </a>
                <p class="link-alt" style="margin-top: 1rem; color: var(--text-muted);">Set <code style="font-size: 0.85em;">APP_DOWNLOAD_URL</code> in your <code style="font-size: 0.85em;">.env</code> to add the store link.</p>
            @endif
        </section>

        <footer>
            <div class="footer-links">
                <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
                <a href="{{ route('terms-and-conditions') }}">Terms &amp; Conditions</a>
                <a href="{{ route('delete-account') }}">Delete Account</a>
                <a href="{{ route('child-safety') }}">Child Safety</a>
            </div>
            <p class="footer-copy">© {{ date('Y') }} Chat Aura. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
