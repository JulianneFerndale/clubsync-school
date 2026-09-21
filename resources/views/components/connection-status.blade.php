{{--
    Global connectivity indicator. Included in every layout so the whole system
    reacts to losing/regaining internet. Self-contained (inline CSS + vanilla JS)
    so it works without a Tailwind rebuild and renders even from a cached page.
--}}
<div id="cs-connection-banner" role="status" aria-live="polite" aria-hidden="true">
    <span class="cs-cb-dot"></span>
    <span id="cs-connection-text">No internet connection — you're offline.</span>
</div>

<style>
    #cs-connection-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 2147483647; /* above sidebars, drawers and sticky headers */
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 16px;
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #ffffff;
        background: #dc2626;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transform: translateY(-100%);
        transition: transform 0.3s ease;
        pointer-events: none;
    }
    #cs-connection-banner.cs-visible { transform: translateY(0); }
    #cs-connection-banner.cs-online { background: #16a34a; }
    .cs-cb-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        flex-shrink: 0;
    }
    #cs-connection-banner:not(.cs-online) .cs-cb-dot {
        animation: cs-cb-pulse 1.2s ease-in-out infinite;
    }
    @keyframes cs-cb-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
</style>

<script>
    (function () {
        if (window.__csConnectionInit) return;   // guard against double include
        window.__csConnectionInit = true;

        var banner = document.getElementById('cs-connection-banner');
        var text   = document.getElementById('cs-connection-text');
        if (!banner) return;

        var online = true;          // assume online until proven otherwise
        var hideTimer = null;
        var probeTimer = null;

        function showOffline() {
            clearTimeout(hideTimer);
            banner.classList.remove('cs-online');
            banner.classList.add('cs-visible');
            banner.setAttribute('aria-hidden', 'false');
            text.textContent = "No internet connection — you're offline.";
        }

        function showBackOnline() {
            banner.classList.add('cs-online', 'cs-visible');
            banner.setAttribute('aria-hidden', 'false');
            text.textContent = 'Back online.';
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                banner.classList.remove('cs-visible');
                banner.setAttribute('aria-hidden', 'true');
            }, 3000);
        }

        // Confirm real reachability — navigator.onLine alone is unreliable
        // (true on captive portals / dead gateways). The /ping route bypasses
        // the service worker cache, so this only succeeds with a live network.
        function probe() {
            return fetch('/ping?t=' + Date.now(), { method: 'HEAD', cache: 'no-store' })
                .then(function (res) { return !!(res && res.ok); })
                .catch(function () { return false; });
        }

        function setOnline(next) {
            if (next === online) return;
            online = next;
            if (online) { showBackOnline(); } else { showOffline(); }
        }

        function evaluate() {
            if (!navigator.onLine) { setOnline(false); return; }
            probe().then(setOnline);
        }

        window.addEventListener('offline', function () { setOnline(false); });
        window.addEventListener('online', evaluate);

        // While offline, keep checking so we notice recovery even without an event.
        probeTimer = setInterval(function () { if (!online) evaluate(); }, 5000);

        // Initial check — only surfaces a banner if we're actually offline.
        if (!navigator.onLine) {
            setOnline(false);
        } else {
            probe().then(function (ok) { if (!ok) setOnline(false); });
        }
    })();
</script>
