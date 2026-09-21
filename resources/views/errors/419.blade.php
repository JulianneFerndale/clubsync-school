@php
    // A stale token does not always mean a dead session: the form may simply have
    // sat open too long while the user stayed signed in. Tell the two apart so the
    // message and the button match what actually happened.
    $stillSignedIn = (bool) session('firebase_user_id');

    // Reuse SessionManager's role → dashboard map rather than repeating it here.
    $homeUrl = $stillSignedIn
        ? app(\App\Services\SessionManager::class)->redirectByRole(auth_role())->getTargetUrl()
        : route('login');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07873e">
    <title>Page Expired — ClubSync</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            /* Warm sunrise wash over SCC green — calm, not alarming. */
            background: linear-gradient(160deg, #fdfaf3 0%, #f4f7f2 55%, #eef4ee 100%);
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            min-height: 100%;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 12px 44px rgba(7, 135, 62, 0.10);
            padding: 38px 34px 30px;
            text-align: center;
        }
        .icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fdf4e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg { width: 36px; height: 36px; stroke: #F9A825; }
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
        .bisaya { color: #6b7280; font-style: italic; }
        .code { display: block; margin-top: 8px; font-size: 11.5px; color: #c3c9c4; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">
            {{-- A gentle clock, not a warning triangle: nothing has gone wrong. --}}
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 6v6l3.75 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </div>

        <span class="kicker">Panagdait</span>

        @if ($stillSignedIn)
            <h1>That page rested a little too long</h1>
            <p class="lead">
                The form you just sent had been open long enough for its security code to
                lapse — so we held it back rather than risk sending it somewhere it
                shouldn't go. You're still signed in, and nothing was lost.
            </p>
            <p>Head back, refresh the page, and send it once more.</p>

            <div class="actions">
                <button type="button" class="btn" onclick="history.back()">Go back and try again</button>
                <a href="{{ $homeUrl }}" class="btn-secondary">Return to my dashboard</a>
            </div>
        @else
            <h1>Welcome back — your session took a rest</h1>
            <p class="lead">
                You were away for a while, so ClubSync quietly signed you out to keep your
                account and your club's records safe. Nothing you've done was lost.
            </p>
            <p>Log in again and pick up right where you left off.</p>

            <div class="actions">
                <a href="{{ route('login') }}" class="btn">Log in again</a>
                <a href="{{ route('welcome') }}" class="btn-secondary">Back to the home page</a>
            </div>
        @endif

        <p class="note">
            @unless ($stillSignedIn)
                For everyone's safety, ClubSync ends a session after
                {{ config('session.lifetime') }} minutes of quiet.
                <br>
            @endunless
            <span class="bisaya">Salamat sa imong pasensya.</span> — Saint Columban College, Pagadian City
            <span class="code">Error 419 · Page Expired</span>
        </p>
    </main>
</body>
</html>
