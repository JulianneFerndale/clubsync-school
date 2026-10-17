@php
    // An error page may render outside the web middleware — an unknown URL never
    // reaches a route, so no session is started — and a 500 page must never throw
    // on its own. So the "who is this" check is defensive: when in doubt, point at
    // the login page, which already forwards a signed-in user to their dashboard.
    try {
        $errSignedIn = (bool) session('firebase_user_id');
        $errHomeUrl  = $errSignedIn
            ? app(\App\Services\SessionManager::class)->redirectByRole(auth_role())->getTargetUrl()
            : route('login');
    } catch (\Throwable) {
        $errSignedIn = false;
        $errHomeUrl  = url('/');
    }

    $errTone = trim($__env->yieldContent('tone')) ?: 'green';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07873e">
    <meta name="robots" content="noindex">
    <title>@yield('title') — ClubSync</title>
    {{-- Inline styles only: this page must still render when the asset build or
         the network is the thing that failed. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            /* Warm sunrise wash over SCC green — calm, not alarming. */
            background: linear-gradient(160deg, #fdfaf3 0%, #f4f7f2 55%, #eef4ee 100%);
            color: #1f2937;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            min-height: 100vh;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            text-decoration: none;
            color: #14532d;
        }
        .brand img { width: 36px; height: 36px; object-fit: contain; }
        .brand-name { display: block; font-size: 16px; font-weight: 800; line-height: 1.1; }
        .brand-school { display: block; font-size: 11.5px; font-weight: 500; color: #6b7280; }
        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 12px 44px rgba(7, 135, 62, 0.10);
            padding: 38px 30px 30px;
            text-align: center;
        }
        .icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg { width: 36px; height: 36px; }
        .tone-green .icon { background: #e8f5ec; }
        .tone-green .icon svg { stroke: #07873e; }
        .tone-amber .icon { background: #fdf4e0; }
        .tone-amber .icon svg { stroke: #F9A825; }
        .tone-red .icon { background: #fef2f2; }
        .tone-red .icon svg { stroke: #dc2626; }
        .kicker {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #07873e;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.3;
            margin: 0 0 12px;
            color: #14532d;
            /* Keep the two-line headline evenly split rather than orphaning a word. */
            text-wrap: balance;
        }
        p {
            font-size: 14.5px;
            line-height: 1.6;
            color: #6b7280;
            margin: 0 0 10px;
            text-wrap: pretty;
        }
        p.lead { color: #4b5563; }
        .detail {
            margin: 16px 0 6px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fdf8ec;
            border: 1px solid #f6e3b4;
            color: #7a5a12;
            font-size: 13.5px;
            line-height: 1.55;
            text-align: left;
        }
        .actions { margin-top: 22px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            color: #ffffff;
            background: #07873e;
            border: none;
            border-radius: 9999px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .btn:hover, .btn:focus { background: #14532d; }
        .btn-secondary {
            display: inline-block;
            margin-top: 14px;
            font-size: 13.5px;
            font-weight: 500;
            color: #07873e;
            text-decoration: none;
            background: none;
            border: none;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-secondary:hover { color: #14532d; text-decoration: underline; }
        .note {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid #f1f3f0;
            font-size: 12.5px;
            line-height: 1.6;
            color: #9ca3af;
        }
        .note-extra { display: block; margin-bottom: 6px; }
        .bisaya { color: #6b7280; font-style: italic; }
        .code { display: block; margin-top: 8px; font-size: 11.5px; color: #c3c9c4; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <a href="{{ $errHomeUrl }}" class="brand" aria-label="ClubSync home">
        <img src="/images/clubsync_logo.png" alt="" onerror="this.remove()">
        <span>
            <span class="brand-name">ClubSync</span>
            <span class="brand-school">Saint Columban College</span>
        </span>
    </a>

    <main class="card tone-{{ $errTone }}">
        <div class="icon" aria-hidden="true">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                @hasSection('icon')
                    @yield('icon')
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                @endif
            </svg>
        </div>

        <span class="kicker">@yield('kicker')</span>
        <h1>@yield('heading')</h1>

        @yield('message')

        <div class="actions">
            @hasSection('actions')
                @yield('actions')
            @else
                @hasSection('back-first')
                    {{-- The request came from a form or upload: retrying is the likely next step. --}}
                    <button type="button" class="btn" onclick="csGoBack()">Go back and try again</button>
                    <a href="{{ $errHomeUrl }}" class="btn-secondary">{{ $errSignedIn ? 'Return to my dashboard' : 'Go to ClubSync' }}</a>
                @else
                    <a href="{{ $errHomeUrl }}" class="btn">{{ $errSignedIn ? 'Back to my dashboard' : 'Go to ClubSync' }}</a>
                    <button type="button" class="btn-secondary" onclick="csGoBack()">Go back to the previous page</button>
                @endif
            @endif
        </div>

        <p class="note">
            @hasSection('note')
                <span class="note-extra">@yield('note')</span>
            @endif
            <span class="bisaya">Salamat sa imong pasensya.</span> — Saint Columban College, Pagadian City
            <span class="code">Error @yield('code') · @yield('title')</span>
        </p>
    </main>

    <script>
        // A page opened straight from a link or bookmark has nowhere to go back to.
        function csGoBack() {
            if (history.length > 1) {
                history.back();
            } else {
                location.href = @json($errHomeUrl);
            }
        }
    </script>
</body>
</html>
