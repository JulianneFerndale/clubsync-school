{{--
    Global loading UI for the whole app. Three modes, driven by vanilla JS
    (no Alpine/Inertia dependency):
      • nav    — a bare green spinner shown while navigating between pages/layouts
      • splash — full-screen branded screen with a message (login, club enrollment)
      • dialog — a card with a message and a Stop button to cancel an operation

    Opt a form in with:  data-loading="dialog|splash" data-loading-message="Adding event"
    Cancelling (Stop) aborts the in-flight request and restores the form.
--}}
<div id="cs-loading" class="fixed inset-0 z-[100] hidden" role="alert" aria-live="assertive" aria-busy="true">

    {{-- Navigation: just the green circle on a soft backdrop --}}
    <div data-cs-layer="nav" class="hidden absolute inset-0 bg-white/55 backdrop-blur-[1px] items-center justify-center">
        <span class="cs-spinner"></span>
    </div>

    {{-- Splash: full-screen brand screen with a message --}}
    <div data-cs-layer="splash" class="hidden absolute inset-0 bg-[#07873e] flex-col items-center justify-center gap-6 px-8 text-center">
        <img src="/images/scc-crest.png" alt="" class="w-20 h-20 object-contain" onerror="this.style.display='none'">
        <span class="cs-spinner cs-spinner--light"></span>
        <p class="text-white font-semibold text-lg" data-cs-message>Loading…</p>
    </div>

    {{-- Dialog: card + message + Stop --}}
    <div data-cs-layer="dialog" class="hidden absolute inset-0 bg-black/40 items-center justify-center p-6">
        <div class="bg-white rounded-3xl shadow-2xl px-10 py-8 w-full max-w-xs flex flex-col items-center gap-4">
            <span class="cs-spinner"></span>
            <p class="text-gray-900 font-semibold text-lg text-center" data-cs-message>Processing…</p>
            <button type="button" id="cs-loading-stop"
                    class="bg-[#07873e] text-[#F9A825] font-bold text-base rounded-full px-12 py-2.5 hover:opacity-90 transition-opacity">
                Stop
            </button>
        </div>
    </div>
</div>

<style>
    .cs-spinner{width:44px;height:44px;border-radius:50%;border:4px solid rgba(27,94,32,.18);border-top-color:#07873e;animation:cs-spin .8s linear infinite}
    .cs-spinner--light{border-color:rgba(255,255,255,.25);border-top-color:#F9A825}
    @keyframes cs-spin{to{transform:rotate(360deg)}}
    /* Show the active layer as a flex container; layers default to display:none via .hidden */
    #cs-loading:not(.hidden) [data-cs-layer]:not(.hidden){display:flex}
</style>

<script>
(function () {
    // Global listeners attach once. The overlay markup is re-rendered on every
    // Turbo navigation, so look the element up fresh at call time — a cached
    // reference would point at a detached node after Turbo swaps the <body>.
    if (window.__csLoadingInit) return;
    window.__csLoadingInit = true;

    const el = () => document.getElementById('cs-loading');

    let active = null;
    let pendingForm = null;
    let navTimer = null;
    let safety = null;

    function show(mode, message) {
        const root = el();
        if (!root) return;
        active = mode;
        ['nav', 'splash', 'dialog'].forEach((k) => {
            const layer = root.querySelector('[data-cs-layer="' + k + '"]');
            if (layer) layer.classList.toggle('hidden', k !== mode);
        });
        if (message) {
            root.querySelectorAll('[data-cs-message]').forEach((m) => { m.textContent = message; });
        }
        root.classList.remove('hidden');
        // Failsafe: never let the overlay stick if no completion event arrives.
        clearTimeout(safety);
        safety = setTimeout(hide, 25000);
    }

    function hide() {
        active = null;
        clearTimeout(navTimer);
        clearTimeout(safety);
        const root = el();
        if (root) root.classList.add('hidden');
        if (pendingForm) {
            delete pendingForm.dataset.csSubmitting;
            pendingForm = null;
        }
        document.querySelectorAll('button[data-cs-disabled]').forEach((b) => {
            b.disabled = false;
            b.removeAttribute('data-cs-disabled');
        });
    }

    window.ClubSyncLoading = { show, hide };

    // Forms that opt in via data-loading
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-loading')) return;
        if (form.dataset.csSubmitting) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

        const mode = form.getAttribute('data-loading') === 'splash' ? 'splash' : 'dialog';
        const msg  = form.getAttribute('data-loading-message') || (mode === 'splash' ? 'Please wait…' : 'Processing…');

        form.dataset.csSubmitting = '1';
        pendingForm = form;
        form.querySelectorAll('button[type="submit"]').forEach((b) => {
            b.disabled = true;
            b.setAttribute('data-cs-disabled', '');
        });
        show(mode, msg);
    }, true);

    // Stop button (delegated — it is re-created on every navigation) + nav spinner
    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;

        if (e.target.closest('#cs-loading-stop')) {
            try { window.stop(); } catch (_) {}
            hide();
            return;
        }

        if (active) return;
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-loading')) return;

        let url;
        try { url = new URL(a.href, window.location.href); } catch (_) { return; }
        if (url.origin !== window.location.origin) return;
        if (url.href === window.location.href || (url.hash && url.pathname === window.location.pathname)) return;

        navTimer = setTimeout(function () { show('nav'); }, 180);
    });

    // Hide once the destination is shown — full reload, bfcache restore, or Turbo render.
    window.addEventListener('pageshow', function (ev) { if (ev.persisted) hide(); });
    window.addEventListener('pagehide', function () { clearTimeout(navTimer); });
    ['turbo:load', 'turbo:render', 'turbo:before-cache'].forEach(function (evt) {
        document.addEventListener(evt, hide);
    });
})();
</script>
