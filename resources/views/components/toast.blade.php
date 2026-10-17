{{--
    A short confirmation that floats at the top of the page and leaves by itself after
    three seconds — for news that needs no answer, such as "Logged in successfully".
    Unlike <x-flash-dialog /> it does not block the page and has no button; a tap
    dismisses it sooner.

    Reads session('toast'): ['title' => ..., 'text' => ...] (text optional). Every
    layout includes it. Self-contained (inline CSS + vanilla JS), and removed before
    Turbo caches the page so Back never replays it.
--}}
@php
    $csToast = session('toast');
    $csToast = is_array($csToast) && filled($csToast['title'] ?? null) ? $csToast : null;
@endphp

@if($csToast)
<div id="cs-toast" class="cs-toast" role="status" aria-live="polite"
     data-title="{{ $csToast['title'] }}" data-text="{{ $csToast['text'] ?? '' }}">
    <span class="cs-toast__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M6 12.5l4 4 8-9"/></svg>
    </span>
    <span class="cs-toast__copy">
        <strong class="cs-toast__title"></strong>
        <span class="cs-toast__text"></span>
    </span>
    <span class="cs-toast__timer" aria-hidden="true"></span>
</div>
@endif

<style>
    .cs-toast{position:fixed;top:1rem;left:50%;z-index:2147483646;display:flex;align-items:center;gap:.75rem;
        width:max-content;max-width:calc(100vw - 2rem);padding:.75rem 1.1rem .8rem .8rem;overflow:hidden;cursor:pointer;
        background:#fff;border-radius:1rem;border:1px solid rgba(7,135,62,.15);
        box-shadow:0 18px 40px -14px rgba(5,60,28,.45),0 4px 12px -4px rgba(0,0,0,.08);
        font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
        transform:translate(-50%,calc(-100% - 1.5rem));opacity:0;
        transition:transform .35s cubic-bezier(.34,1.4,.64,1),opacity .25s ease}
    .cs-toast.is-shown{transform:translate(-50%,0);opacity:1}
    .cs-toast.is-leaving{transform:translate(-50%,calc(-100% - 1.5rem));opacity:0;transition:transform .25s ease-in,opacity .25s ease-in}
    .cs-toast__icon{flex-shrink:0;width:2.1rem;height:2.1rem;border-radius:50%;background:#07873e;
        display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 3px #fde8b0}
    .cs-toast__icon svg{width:1.2rem;height:1.2rem;fill:none;stroke:#fff;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
    .cs-toast__copy{display:flex;flex-direction:column;min-width:0}
    .cs-toast__title{font-size:.9rem;font-weight:800;color:#05622d;line-height:1.25}
    .cs-toast__text{font-size:.8rem;color:#4b5563;line-height:1.3}
    .cs-toast__text:empty{display:none}
    .cs-toast__timer{position:absolute;left:0;bottom:0;height:3px;width:100%;background:#F9A825;transform-origin:left}
    .cs-toast.is-shown .cs-toast__timer{transform:scaleX(0);transition:transform var(--cs-toast-ms,3000ms) linear}
    @media (prefers-reduced-motion:reduce){
        .cs-toast,.cs-toast.is-shown,.cs-toast.is-leaving{transform:translate(-50%,0);transition:opacity .2s ease}
    }
</style>

<script>
(function () {
    // Listeners attach once; the toast itself is re-rendered with each page.
    if (window.__csToastInit) return;
    window.__csToastInit = true;

    const SHOWN_FOR = 3000;
    let timer = null;

    function hide(toast) {
        if (!toast.isConnected || toast.classList.contains('is-leaving')) return;
        clearTimeout(timer);
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 260);
    }

    function show() {
        const toast = document.getElementById('cs-toast');
        if (!toast || toast.dataset.shown) return;
        toast.dataset.shown = '1';

        // Filled in now, not by the server, so screen readers announce it as it appears.
        toast.querySelector('.cs-toast__title').textContent = toast.dataset.title;
        toast.querySelector('.cs-toast__text').textContent = toast.dataset.text;
        toast.style.setProperty('--cs-toast-ms', SHOWN_FOR + 'ms');

        toast.getBoundingClientRect(); // commit the hidden position before sliding in
        requestAnimationFrame(() => toast.classList.add('is-shown'));

        toast.addEventListener('click', () => hide(toast));
        timer = setTimeout(() => hide(toast), SHOWN_FOR);
    }

    document.addEventListener('turbo:load', show);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show);
    else show();

    // Never keep it in Turbo's snapshot, or Back would show it again.
    document.addEventListener('turbo:before-cache', () => {
        clearTimeout(timer);
        document.getElementById('cs-toast')?.remove();
    });
})();
</script>
