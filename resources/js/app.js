import '@hotwired/turbo'; // SPA-style navigation (no full page reloads); auto-starts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ── PWA service worker ──────────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((reg) => reg.update())
            .catch(() => {});
    });

    // When a new worker takes control (e.g. after an asset rebuild), reload once
    // so the page uses the fresh worker + assets. This self-heals the "unstyled
    // page from a stale cache" problem instead of requiring a manual cache clear.
    //
    // Only arm this when a controller already exists: on a first-ever visit the SW's
    // clients.claim() fires controllerchange with no prior controller, and reloading
    // there is a pointless full refresh for every new visitor. We only want to reload
    // on a genuine controller *swap* (an update), which can only happen once controlled.
    if (navigator.serviceWorker.controller) {
        let reloading = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (reloading) return;
            reloading = true;
            window.location.reload();
        });
    }
}

// ── Turbo: let the browser handle file downloads natively ───────────────────
// Turbo would otherwise try to "render" an attachment response. Any link to a
// download route (…/download, …/letter, …/pdf, …/xlsx, …/receipt/…) is taken over the wire.
document.addEventListener('turbo:click', (event) => {
    const url = event.detail?.url ?? event.target?.href ?? '';
    let path;
    try { path = new URL(url, window.location.href).pathname; } catch { return; }
    if (/\/(download|letter|pdf|xlsx|receipt)(?:[/?]|$)/i.test(path)) {
        event.preventDefault();
        window.location.href = url; // native request → browser downloads the file
    }
});

// ── Real-time notifications and chat signals via Laravel Reverb ─────────────
//
// Sign-in and sign-out do full page loads (data-turbo="false"), but the listener
// does not rely on that: after every Turbo render it compares the signed-in user in
// <meta name="cs-user-id"> with the user it is subscribed for, and reconnects when
// they differ. Without this, a Turbo sign-in left the new user with no live updates,
// and the next person on a shared computer kept receiving the previous user's.
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const metaContent = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const signedInUserId = () => parseInt(metaContent('cs-user-id'), 10) || 0;

let liveUserId = 0;

// ── When the live connection cannot be reached ──────────────────────────────
// Not every deployment runs Reverb (Laravel Cloud and the Render image have none, and a
// local run without `composer run dev` has none either). Rather than going quiet, the
// page polls the counts while its socket is down and toasts what arrived, so a
// notification still reaches the person within about ten seconds. A hidden tab skips
// its turn — coming back after more than ten seconds re-checks at once (see
// visibilitychange below) — so background tabs cost nothing. Polling stops once the socket connects.
const POLL_MS = 10000;
let pollTimer = null;
let lastNotificationId = null; // newest one already on screen; null until first read

function startPolling() {
    if (pollTimer || !liveUserId) return;
    resync(); // sets the baseline, so what was already there is not toasted
    pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') resync();
    }, POLL_MS);
}

function stopPolling() {
    clearInterval(pollTimer);
    pollTimer = null;
}

function syncLiveUser() {
    const userId = signedInUserId();
    if (userId !== liveUserId) connectFor(userId);
}

function connectFor(userId) {
    if (window.Echo) {
        window.Echo.disconnect();
        window.Echo = null;
    }
    document.getElementById('cs-toast-host')?.remove();
    stopPolling();
    lastNotificationId = null;
    liveUserId = userId;

    if (!userId) return;
    if (!reverbKey) { startPolling(); return; }

    // Connect to Reverb relative to however this page was loaded, so the same
    // build works locally (http://…:8080) and behind an HTTPS tunnel/proxy
    // (wss on 443, where a front proxy routes /app to Reverb) with no rebuild.
    const isHttps = window.location.protocol === 'https:';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT) || 8080,
        wssPort: 443,
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
        // Read the CSRF token when each channel is authorised, not once at startup:
        // signing in regenerates the session token.
        channelAuthorization: {
            customHandler: ({ socketId, channelName }, callback) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': metaContent('csrf-token'),
                    },
                    body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
                })
                    .then(async (res) => {
                        if (!res.ok) throw new Error(`Channel authorisation failed (${res.status})`);
                        callback(null, await res.json());
                    })
                    .catch((error) => callback(error, null));
            },
        },
    });

    window.Echo.private(`notifications.${userId}`)
        .listen('.notification.created', (e) => {
            lastNotificationId = Math.max(lastNotificationId ?? 0, e.id || 0);
            updateBadges('js-notif-badge', e.unread);
            showToast(e);
            scheduleLiveRefresh(e.club_id);
        })
        // Read on another tab or device: drop this tab's badge too.
        .listen('.notification.read', (e) => updateBadges('js-notif-badge', e.unread));

    // Private messages. The signal carries ids and an unread count only — never the
    // text or the sender — so the toast stays content-free on a shared screen, and
    // an open conversation fetches the message itself (see chat/show.blade.php).
    window.Echo.private(`chat.${userId}`)
        .listen('.chat.activity', (e) => {
            const viewingThisThread = window.__csChatThread?.conversationId === e.conversation_id;

            // null means the count did not change (e.g. your own message). In an open thread
            // the conversation's own fetch marks the message read and sets the badge, so the
            // brief "1 unread" flash is skipped here.
            if (typeof e.unread === 'number' && !(viewingThisThread && e.type === 'message')) {
                updateBadges('js-chat-badge', e.unread);
            }
            document.dispatchEvent(new CustomEvent('cs:chat-activity', { detail: e }));

            const onInbox = !!document.getElementById('chat-inbox'); // the inbox updates itself
            if (e.type === 'message' && e.sender_id !== userId && !viewingThisThread && !onInbox) {
                showToast({
                    title: 'New private message',
                    body: 'Open Messages to read it.',
                    action_url: `/chat/${e.conversation_id}`,
                });
            }
        });

    // A dropped connection loses every event sent while it was down. When it comes
    // back, re-read the counts and refresh what is on screen.
    // While it cannot connect at all ('unavailable' after its retries, 'failed' when
    // WebSockets are unsupported), fall back to polling until it does.
    let connectedOnce = false;
    window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        if (current === 'unavailable' || current === 'failed') startPolling();
        if (current !== 'connected') return;
        stopPolling();
        if (connectedOnce) resync();
        connectedOnce = true;
    });
}

// Turbo fires turbo:load for the first page and after every navigation.
document.addEventListener('turbo:load', syncLiveUser);
if (document.readyState !== 'loading') syncLiveUser();

// ── Logging out in every tab ────────────────────────────────────────────────
// Logging out in one tab ends the session for the whole browser, but another open
// tab would still show the account and, over its live connection, keep receiving
// that account's notifications. The page shown after logout (marked by
// <meta name="cs-logged-out">, so the session is already gone) tells the others; they
// drop the connection and go to the sign-in page. Telling them from the Logout click
// was too early: they reached /login while still signed in and were sent back in.
const authChannel = 'BroadcastChannel' in window ? new BroadcastChannel('clubsync-auth') : null;

if (metaContent('cs-logged-out')) authChannel?.postMessage('logged-out');

authChannel?.addEventListener('message', (event) => {
    if (event.data !== 'logged-out' || !signedInUserId()) return;
    connectFor(0); // disconnects from Reverb
    window.location.replace('/login');
});

// ── Keeping badges honest ───────────────────────────────────────────────────
// Back/Forward restores a cached copy of the page, with the counts it had then.
let restoring = false;
document.addEventListener('turbo:visit', (e) => { restoring = e.detail?.action === 'restore'; });
document.addEventListener('turbo:load', () => {
    if (restoring) {
        restoring = false;
        resync();
    }
});

// A tab left in the background can miss changes made on another device.
let hiddenAt = 0;
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        hiddenAt = Date.now();
    } else if (hiddenAt && Date.now() - hiddenAt > 10000) {
        resync();
    }
});

// Toasts are transient; never keep them in Turbo's page snapshots.
document.addEventListener('turbo:before-cache', () => document.getElementById('cs-toast-host')?.remove());

let resyncTimer = null;
function resync() {
    clearTimeout(resyncTimer);
    resyncTimer = setTimeout(async () => {
        if (!liveUserId) return;
        try {
            const res = await fetch('/live/counts', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const counts = await res.json();
            const shownNotifications = badgeValue('js-notif-badge');

            // Toast a notification that arrived while no live event could (socket down,
            // or it came in while the connection was dropped). The first read only sets
            // the baseline, so what was already waiting is not re-announced.
            const latestId = counts.latest?.id || 0;
            if (lastNotificationId === null) {
                lastNotificationId = latestId;
            } else if (latestId > lastNotificationId) {
                lastNotificationId = latestId;
                showToast(counts.latest);
            }

            updateBadges('js-notif-badge', counts.notifications);
            updateBadges('js-chat-badge', counts.chat);
            document.dispatchEvent(new CustomEvent('cs:live-resync', { detail: counts }));

            if (shownNotifications !== counts.notifications) scheduleLiveRefresh();
        } catch { /* offline — the connection banner already says so */ }
    }, 150);
}

function badgeValue(cls) {
    const el = document.querySelector('.' + cls);
    if (!el) return null;
    if (el.style.display === 'none') return 0;
    const n = parseInt(el.textContent, 10);
    return Number.isNaN(n) ? null : n;
}

// Update every badge of a kind in the chrome (sidebar, drawer, header, bottom nav).
function updateBadges(cls, count) {
    const n = Number(count) || 0;
    document.querySelectorAll('.' + cls).forEach((el) => {
        el.textContent = n > 9 ? '9+' : String(n);
        el.style.display = n > 0 ? '' : 'none';
    });
}

// ── Live page refresh ───────────────────────────────────────────────────────
// Pages that list club updates opt in with @section('live-refresh', 'any' | 'club:ID').
// When a notification arrives, the content inside #cs-page is fetched again and
// swapped in place (scroll position and navigation stay put), so a new activity or
// announcement appears without a reload. A page with unsaved form input is left
// alone until the input is submitted or cleared.
let refreshTimer = null;
let refreshing = false;
let refreshAgain = false;
let navigating = false; // between a Turbo visit starting and the next page rendering

document.addEventListener('turbo:before-visit', () => { navigating = true; clearTimeout(refreshTimer); });
document.addEventListener('turbo:submit-start', () => { navigating = true; clearTimeout(refreshTimer); });
document.addEventListener('turbo:load', () => { navigating = false; });
document.addEventListener('turbo:submit-end', (e) => { if (!e.detail?.success) navigating = false; });

function scheduleLiveRefresh(clubId) {
    if (navigating) return;
    const scope = document.getElementById('cs-page')?.dataset.liveRefresh;
    if (!scope) return;
    if (scope.startsWith('club:') && clubId && Number(scope.slice(5)) !== Number(clubId)) return;

    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(refreshLivePage, 300);
}

async function refreshLivePage() {
    const current = document.getElementById('cs-page');
    if (navigating || !current?.dataset.liveRefresh) return;
    if (refreshing) { refreshAgain = true; return; }
    if (hasUnsavedInput(current)) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshLivePage, 5000);
        return;
    }

    refreshing = true;
    const url = window.location.href;
    try {
        const res = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        // A redirect means the session ended or access changed; leave that to normal navigation.
        if (!res.ok || res.redirected || window.location.href !== url) return;

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const fresh = doc.getElementById('cs-page');
        const page = document.getElementById('cs-page');
        if (navigating || !fresh || page !== current || hasUnsavedInput(page)) return;

        page.innerHTML = fresh.innerHTML;
        for (const cls of ['js-notif-badge', 'js-chat-badge']) {
            const badge = doc.querySelector('.' + cls);
            if (badge) updateBadges(cls, badge.style.display === 'none' ? 0 : parseInt(badge.textContent, 10) || 0);
        }
        document.dispatchEvent(new CustomEvent('cs:live-refreshed'));
    } catch { /* offline — the next update tries again */ }
    finally {
        refreshing = false;
        if (refreshAgain) { refreshAgain = false; scheduleLiveRefresh(); }
    }
}

document.addEventListener('turbo:before-render', () => { clearTimeout(refreshTimer); refreshAgain = false; });

function hasUnsavedInput(root) {
    const active = document.activeElement;
    if (active && root.contains(active) && active.matches('input:not([type=hidden]):not([type=submit]):not([type=button]), textarea, select')) {
        return true;
    }
    return Array.from(root.querySelectorAll('input, textarea, select')).some((el) => {
        if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return false;
        if (el.type === 'checkbox' || el.type === 'radio') return el.checked !== el.defaultChecked;
        if (el.tagName === 'SELECT') return Array.from(el.options).some((o) => o.selected !== o.defaultSelected);
        return el.value !== el.defaultValue;
    });
}

// Lightweight, self-contained toast (no markup required in Blade).
function showToast(payload) {
    let host = document.getElementById('cs-toast-host');
    if (!host) {
        host = document.createElement('div');
        host.id = 'cs-toast-host';
        host.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:120;display:flex;flex-direction:column;gap:.5rem;max-width:22rem;';
        document.body.appendChild(host);
    }

    // Keep the stack short when several updates arrive at once.
    while (host.children.length >= 3) host.firstElementChild.remove();

    const toast = document.createElement('a');
    toast.href = payload.action_url || '#';
    toast.style.cssText = 'display:block;background:#fff;border-left:4px solid #07873e;border-radius:.75rem;box-shadow:0 10px 25px rgba(0,0,0,.15);padding:.75rem 1rem;text-decoration:none;color:#111827;transform:translateX(120%);transition:transform .25s ease;';

    const title = document.createElement('p');
    title.style.cssText = 'font-weight:700;font-size:.875rem;margin:0 0 .15rem;';
    title.textContent = payload.title || 'New notification';

    const body = document.createElement('p');
    body.style.cssText = 'font-size:.8rem;color:#4b5563;margin:0;line-height:1.3;';
    body.textContent = payload.body || '';

    toast.appendChild(title);
    toast.appendChild(body);
    host.appendChild(toast);

    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

    const dismiss = () => {
        toast.style.transform = 'translateX(120%)';
        setTimeout(() => toast.remove(), 250);
    };
    setTimeout(dismiss, 6000);
    toast.addEventListener('click', dismiss);
}
