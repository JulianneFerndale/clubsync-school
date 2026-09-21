<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07873e">
    <title>No Internet Connection — ClubSync</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            min-height: 100%;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 40px 32px;
            text-align: center;
        }
        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: #fef2f2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg { width: 36px; height: 36px; stroke: #dc2626; }
        h1 { font-size: 20px; font-weight: 700; margin: 0 0 10px; color: #111827; }
        p { font-size: 14px; line-height: 1.6; color: #6b7280; margin: 0 0 24px; }
        .brand { color: #07873e; font-weight: 600; }
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
            background: #07873e;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        button:hover { background: #154a19; }
        button:disabled { opacity: 0.6; cursor: default; }
        .status {
            margin-top: 18px;
            font-size: 13px;
            font-weight: 500;
            color: #9ca3af;
        }
        .status.online { color: #07873e; }
        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #dc2626;
            margin-right: 6px;
            vertical-align: middle;
        }
        .status.online .dot { background: #16a34a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M18.364 5.636a9 9 0 010 12.728m0 0l-12.728-12.728m12.728 12.728L5.636 5.636M3 3l18 18"/>
            </svg>
        </div>
        <h1>No internet connection</h1>
        <p>
            You appear to be offline. <span class="brand">ClubSync</span> needs an internet
            connection to load this page. Check your Wi-Fi or mobile data and try again.
        </p>
        <button id="retry" type="button" onclick="window.location.reload()">
            Try again
        </button>
        <div class="status" id="status">
            <span class="dot"></span><span id="status-text">Waiting for connection…</span>
        </div>
    </div>

    <script>
        (function () {
            var status = document.getElementById('status');
            var statusText = document.getElementById('status-text');

            function reflect() {
                if (navigator.onLine) {
                    status.classList.add('online');
                    statusText.textContent = 'Back online — reloading…';
                    // Give the network a moment to fully settle, then reload.
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    status.classList.remove('online');
                    statusText.textContent = 'Waiting for connection…';
                }
            }

            window.addEventListener('online', reflect);
            window.addEventListener('offline', reflect);

            // Actively probe in case the browser's online flag is unreliable
            // (e.g. captive portals report "online" without real connectivity).
            setInterval(function () {
                if (!navigator.onLine) return;
                fetch('/ping?t=' + Date.now(), { method: 'HEAD', cache: 'no-store' })
                    .then(function (res) { if (res && res.ok) window.location.reload(); })
                    .catch(function () { /* still offline */ });
            }, 5000);

            reflect();
        })();
    </script>
</body>
</html>
