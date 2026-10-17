{{--
    The result of an action, shown once as an SCC-styled dialog: success, info, a
    refusal, or the validation errors of a form. Every layout includes it, so a page
    never has to render its own flash banner and none can forget to.

    Reads the session flashes (success / error / warning / info) and the error bag.
    Success and info close by themselves after a few seconds; a problem stays until
    the person dismisses it. Self-contained (inline CSS + vanilla JS), and removed
    before Turbo caches the page so Back never replays it.
--}}
@php
    $csFlash = collect();

    foreach (['error' => 'error', 'warning' => 'warning', 'success' => 'success', 'info' => 'info'] as $key => $kind) {
        if (filled($value = session($key)) && is_string($value)) {
            $csFlash->push(['kind' => $kind, 'text' => $value]);
        }
    }

    $csErrors = isset($errors) ? $errors->all() : [];

    // Most severe first: a problem outranks a success shown alongside it.
    $csKind = $csErrors !== [] || $csFlash->contains('kind', 'error') ? 'error'
        : ($csFlash->contains('kind', 'warning') ? 'warning'
        : ($csFlash->contains('kind', 'success') ? 'success' : 'info'));

    $csTitle = match ($csKind) {
        'error'   => $csErrors !== [] && ! $csFlash->contains('kind', 'error') ? 'Please check the details' : 'That did not go through',
        'warning' => 'Please take note',
        'success' => 'Success!',
        default   => 'Heads up',
    };

    $csLines = $csFlash->pluck('text')->merge(array_slice($csErrors, 0, 4))->unique()->values();
    $csMore  = max(count($csErrors) - 4, 0);
@endphp

@if($csLines->isNotEmpty())
<dialog id="cs-flash" class="cs-flash cs-flash--{{ $csKind }}" aria-labelledby="cs-flash-title" aria-describedby="cs-flash-body"
        data-autoclose="{{ in_array($csKind, ['success', 'info'], true) ? 4500 : 0 }}">
    <div class="cs-flash__card" role="document">
        <div class="cs-flash__head">
            <span class="cs-flash__glow" aria-hidden="true"></span>
            <span class="cs-flash__badge" aria-hidden="true">
                @if($csKind === 'success')
                    <svg viewBox="0 0 52 52"><path class="cs-flash__draw" d="M14 27.5l8 8 16-17"/></svg>
                @elseif($csKind === 'info')
                    <svg viewBox="0 0 52 52"><path class="cs-flash__draw" d="M26 23v14"/><circle cx="26" cy="15.5" r="2.6" class="cs-flash__dot"/></svg>
                @else
                    <svg viewBox="0 0 52 52"><path class="cs-flash__draw" d="M26 13v16"/><circle cx="26" cy="37" r="2.6" class="cs-flash__dot"/></svg>
                @endif
            </span>
        </div>

        <div class="cs-flash__content">
            <h2 id="cs-flash-title" class="cs-flash__title">{{ $csTitle }}</h2>
            <div id="cs-flash-body" class="cs-flash__body">
                @if($csLines->count() === 1)
                    <p>{{ $csLines->first() }}</p>
                @else
                    <ul>
                        @foreach($csLines as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if($csMore > 0)
                    <p class="cs-flash__more">…and {{ $csMore }} more — see the highlighted fields.</p>
                @endif
            </div>

            <form method="dialog">
                <button type="submit" class="cs-flash__ok" autofocus>{{ $csKind === 'error' ? 'Got it' : 'OK' }}</button>
            </form>
        </div>

        <span class="cs-flash__timer" aria-hidden="true"><span></span></span>
    </div>
</dialog>
@endif

<style>
    .cs-flash{--g:#07873e;--g-dark:#05622d;--gold:#F9A825;--gold-soft:#fde8b0;
        border:0;padding:0;background:transparent;max-width:min(24rem,calc(100vw - 2rem));width:100%;overflow:visible;margin:auto}
    .cs-flash::backdrop{background:rgba(5,40,20,.45);backdrop-filter:blur(2px);animation:cs-flash-fade .25s ease both}
    .cs-flash__card{position:relative;background:#fff;border-radius:1.5rem;overflow:hidden;
        box-shadow:0 24px 60px -12px rgba(5,60,28,.45),0 0 0 1px rgba(7,135,62,.08);
        animation:cs-flash-pop .5s cubic-bezier(.34,1.56,.64,1) both}
    .cs-flash__head{position:relative;height:5.5rem;display:flex;align-items:flex-end;justify-content:center;overflow:visible;
        background:radial-gradient(120% 140% at 50% -20%,#0aa34c 0%,var(--g) 45%,var(--g-dark) 100%)}
    .cs-flash--error .cs-flash__head,.cs-flash--warning .cs-flash__head{
        background:radial-gradient(120% 140% at 50% -20%,#ffd66b 0%,var(--gold) 50%,#e08e00 100%)}
    /* A thin gold rule under the green band — the SCC green and gold together */
    .cs-flash__head::after{content:"";position:absolute;left:0;right:0;bottom:0;height:4px;background:var(--gold)}
    .cs-flash--error .cs-flash__head::after,.cs-flash--warning .cs-flash__head::after{background:var(--g)}
    .cs-flash__glow{position:absolute;left:50%;bottom:-2.75rem;margin-left:-2.75rem;width:5.5rem;height:5.5rem;border-radius:50%;
        border:3px solid var(--gold);opacity:0;animation:cs-flash-ring 1.1s .35s ease-out both}
    .cs-flash--error .cs-flash__glow,.cs-flash--warning .cs-flash__glow{border-color:var(--g)}
    .cs-flash__badge{position:relative;z-index:1;margin-bottom:-2.25rem;width:4.5rem;height:4.5rem;border-radius:50%;
        background:#fff;display:flex;align-items:center;justify-content:center;
        box-shadow:0 0 0 4px var(--gold),0 10px 24px -6px rgba(0,0,0,.3);animation:cs-flash-badge .55s .15s cubic-bezier(.34,1.56,.64,1) both}
    .cs-flash--error .cs-flash__badge,.cs-flash--warning .cs-flash__badge{box-shadow:0 0 0 4px var(--g),0 10px 24px -6px rgba(0,0,0,.3)}
    .cs-flash__badge svg{width:2.75rem;height:2.75rem;fill:none;stroke:var(--g);stroke-width:5;stroke-linecap:round;stroke-linejoin:round}
    .cs-flash--error .cs-flash__badge svg,.cs-flash--warning .cs-flash__badge svg{stroke:#b45309}
    .cs-flash__dot{fill:var(--g);stroke:none;animation:cs-flash-fade .3s .6s both}
    .cs-flash--error .cs-flash__dot,.cs-flash--warning .cs-flash__dot{fill:#b45309}
    .cs-flash__draw{stroke-dasharray:48;stroke-dashoffset:48;animation:cs-flash-draw .45s .45s ease-out forwards}
    .cs-flash--error .cs-flash__badge{animation:cs-flash-badge .55s .15s cubic-bezier(.34,1.56,.64,1) both,cs-flash-shake .45s .75s ease-in-out}
    .cs-flash__content{padding:3rem 1.75rem 1.6rem;text-align:center}
    .cs-flash__title{margin:0 0 .4rem;font-size:1.2rem;font-weight:800;color:var(--g-dark);letter-spacing:-.01em}
    .cs-flash--error .cs-flash__title,.cs-flash--warning .cs-flash__title{color:#92400e}
    .cs-flash__body{font-size:.9rem;line-height:1.5;color:#374151}
    .cs-flash__body p{margin:0}
    .cs-flash__body ul{margin:.25rem 0 0;padding:0;list-style:none;text-align:left;display:grid;gap:.35rem}
    .cs-flash__body li{position:relative;padding-left:1.1rem}
    .cs-flash__body li::before{content:"";position:absolute;left:0;top:.55em;width:.45rem;height:.45rem;border-radius:50%;background:var(--gold)}
    .cs-flash__more{margin-top:.5rem!important;font-size:.8rem;color:#6b7280}
    .cs-flash__ok{margin-top:1.35rem;border:0;cursor:pointer;background:var(--g);color:var(--gold);font-weight:800;font-size:.95rem;
        border-radius:9999px;padding:.7rem 3rem;box-shadow:0 8px 18px -8px rgba(7,135,62,.8);transition:transform .15s ease,filter .15s ease}
    .cs-flash__ok:hover{filter:brightness(1.08);transform:translateY(-1px)}
    .cs-flash__ok:focus-visible{outline:3px solid var(--gold);outline-offset:3px}
    .cs-flash__timer{position:absolute;left:0;right:0;bottom:0;height:4px;background:var(--gold-soft)}
    .cs-flash__timer>span{display:block;height:100%;width:100%;background:var(--gold);transform-origin:left}
    .cs-flash[data-autoclose="0"] .cs-flash__timer{display:none}
    .cs-flash.is-closing .cs-flash__card{animation:cs-flash-out .2s ease-in both}
    .cs-flash.is-closing::backdrop{animation:cs-flash-fade .2s ease-in reverse both}
    @keyframes cs-flash-fade{from{opacity:0}to{opacity:1}}
    @keyframes cs-flash-pop{from{opacity:0;transform:translateY(24px) scale(.9)}to{opacity:1;transform:none}}
    @keyframes cs-flash-out{to{opacity:0;transform:translateY(12px) scale(.94)}}
    @keyframes cs-flash-badge{from{transform:scale(0) rotate(-30deg)}to{transform:none}}
    @keyframes cs-flash-draw{to{stroke-dashoffset:0}}
    @keyframes cs-flash-ring{0%{opacity:.9;transform:scale(.8)}100%{opacity:0;transform:scale(1.6)}}
    @keyframes cs-flash-shake{0%,100%{transform:none}25%{transform:translateX(-5px)}50%{transform:translateX(5px)}75%{transform:translateX(-3px)}}
    @media (prefers-reduced-motion:reduce){
        .cs-flash *,.cs-flash::backdrop{animation-duration:.01ms!important;animation-delay:0s!important}
        .cs-flash__draw{stroke-dashoffset:0}
    }
</style>

<script>
(function () {
    // Listeners attach once; the dialog itself is re-rendered with each page.
    if (window.__csFlashInit) return;
    window.__csFlashInit = true;

    let timer = null;

    function close(dialog) {
        if (!dialog.open || dialog.classList.contains('is-closing')) return;
        clearTimeout(timer);
        dialog.classList.add('is-closing');
        setTimeout(() => { dialog.close(); dialog.remove(); }, 190);
    }

    function open() {
        const dialog = document.getElementById('cs-flash');
        if (!dialog || dialog.dataset.shown) return;
        dialog.dataset.shown = '1';

        try { dialog.showModal(); } catch (_) { dialog.setAttribute('open', ''); }

        // OK and Esc both go through close() so the exit animation plays.
        dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(dialog); });
        dialog.querySelector('form[method="dialog"]')?.addEventListener('submit', (e) => { e.preventDefault(); close(dialog); });
        // A click on the backdrop (outside the card) dismisses it too.
        dialog.addEventListener('click', (e) => { if (e.target === dialog) close(dialog); });

        const ms = parseInt(dialog.dataset.autoclose, 10) || 0;
        if (ms > 0) {
            const bar = dialog.querySelector('.cs-flash__timer > span');
            let remaining = ms, started = Date.now();
            const run = () => {
                started = Date.now();
                if (bar) {
                    bar.style.transform = 'scaleX(' + (remaining / ms) + ')';
                    bar.getBoundingClientRect(); // commit the start width before animating
                    bar.style.transition = 'transform ' + remaining + 'ms linear';
                    requestAnimationFrame(() => { bar.style.transform = 'scaleX(0)'; });
                }
                timer = setTimeout(() => close(dialog), remaining);
            };
            const pause = () => {
                clearTimeout(timer);
                remaining = Math.max(remaining - (Date.now() - started), 400);
                if (bar) { bar.style.transition = 'none'; bar.style.transform = 'scaleX(' + (remaining / ms) + ')'; }
            };
            // Hovering the card holds it open while it is being read.
            const card = dialog.querySelector('.cs-flash__card');
            card?.addEventListener('mouseenter', pause);
            card?.addEventListener('mouseleave', run);
            run();
        }
    }

    // For a result only the browser knows — no connection, so the server never
    // answered: the same dialog as above, built here instead of from the session.
    const badges = {
        success: '<path class="cs-flash__draw" d="M14 27.5l8 8 16-17"/>',
        info:    '<path class="cs-flash__draw" d="M26 23v14"/><circle cx="26" cy="15.5" r="2.6" class="cs-flash__dot"/>',
        problem: '<path class="cs-flash__draw" d="M26 13v16"/><circle cx="26" cy="37" r="2.6" class="cs-flash__dot"/>',
    };

    window.csFlash = function (kind, title, text) {
        clearTimeout(timer);
        document.getElementById('cs-flash')?.remove();

        const quiet = kind === 'success' || kind === 'info';
        const dialog = document.createElement('dialog');
        dialog.id = 'cs-flash';
        dialog.className = 'cs-flash cs-flash--' + kind;
        dialog.setAttribute('aria-labelledby', 'cs-flash-title');
        dialog.setAttribute('aria-describedby', 'cs-flash-body');
        dialog.dataset.autoclose = quiet ? '4500' : '0';
        dialog.innerHTML =
            '<div class="cs-flash__card" role="document">' +
                '<div class="cs-flash__head">' +
                    '<span class="cs-flash__glow" aria-hidden="true"></span>' +
                    '<span class="cs-flash__badge" aria-hidden="true"><svg viewBox="0 0 52 52">' + (badges[kind] || badges.problem) + '</svg></span>' +
                '</div>' +
                '<div class="cs-flash__content">' +
                    '<h2 id="cs-flash-title" class="cs-flash__title"></h2>' +
                    '<div id="cs-flash-body" class="cs-flash__body"><p></p></div>' +
                    '<form method="dialog"><button type="submit" class="cs-flash__ok" autofocus>' + (quiet ? 'OK' : 'Got it') + '</button></form>' +
                '</div>' +
                '<span class="cs-flash__timer" aria-hidden="true"><span></span></span>' +
            '</div>';
        dialog.querySelector('.cs-flash__title').textContent = title;
        dialog.querySelector('.cs-flash__body p').textContent = text;

        document.body.appendChild(dialog);
        open();
    };

    document.addEventListener('turbo:load', open);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', open);
    else open();

    // Never keep it in Turbo's snapshot, or Back would show the old result again.
    document.addEventListener('turbo:before-cache', () => {
        clearTimeout(timer);
        document.getElementById('cs-flash')?.remove();
    });
})();
</script>
