@props([
    'href',
    'title' => 'No non-academic clubs yet',
    'text'  => 'Sports, arts, faith and service clubs are open to every student. Find one that fits you.',
    'cta'   => 'Browse Non-Academic Clubs',
])
{{--
    The friendly "you have not joined a club yet" state on the dashboards: a small orbit
    of club icons (music, sports, arts, service) circling a "+", in the welcome page's
    style, with a button to go and find one.

    href: where the button leads (the club list to join from).
    Self-contained (inline CSS, no JS); still and plain under prefers-reduced-motion.
--}}
<div {{ $attributes->merge(['class' => 'ce-card']) }}>
    <div class="ce-scene" aria-hidden="true">
        <span class="ce-glow"></span>
        <span class="ce-ring"></span>

        {{-- The satellites ride a slowly turning orbit; each one turns back so its icon stays upright. --}}
        <span class="ce-orbit">
            @foreach ([
                ['a' => '0deg',   'd' => '0s',   'icon' => 'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z'],
                ['a' => '90deg',  'd' => '.6s',  'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
                ['a' => '180deg', 'd' => '1.2s', 'icon' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
                ['a' => '270deg', 'd' => '1.8s', 'icon' => 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0'],
            ] as $satellite)
                <span class="ce-satellite" style="--a: {{ $satellite['a'] }}; --d: {{ $satellite['d'] }}">
                    <span>
                        <svg viewBox="0 0 24 24"><path d="{{ $satellite['icon'] }}"/></svg>
                    </span>
                </span>
            @endforeach
        </span>

        <span class="ce-core">
            <svg viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </span>
    </div>

    <p class="ce-title">{{ $title }}</p>
    <p class="ce-text">{{ $text }}</p>

    <a href="{{ $href }}" class="ce-cta">
        {{ $cta }}
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
    </a>
</div>

@once
<style>
    .ce-card{--g:#07873e;--gold:#F9A825;--orbit:8.5rem;
        display:flex;flex-direction:column;align-items:center;text-align:center;padding:1.5rem 1.25rem 1.4rem;
        background:linear-gradient(180deg,#f3fbf6 0%,#fff 70%);border:1.5px dashed rgba(7,135,62,.28);border-radius:1.25rem;
        animation:ce-rise .55s cubic-bezier(.22,1,.36,1) both}
    /* The satellites stick out of the orbit by half their size, so the scene keeps that much room around it. */
    .ce-scene{position:relative;width:var(--orbit);height:var(--orbit);margin:.85rem 0 1.85rem;display:flex;align-items:center;justify-content:center}
    .ce-glow{position:absolute;inset:18%;border-radius:9999px;
        background:radial-gradient(circle,rgba(249,168,37,.42) 0%,rgba(249,168,37,0) 70%);animation:ce-breathe 4.5s ease-in-out infinite}
    .ce-ring{position:absolute;inset:0;border-radius:9999px;border:2px dashed rgba(7,135,62,.3);animation:ce-spin 40s linear infinite reverse}
    .ce-orbit{position:absolute;inset:0;animation:ce-spin 24s linear infinite}
    .ce-satellite{position:absolute;left:50%;top:50%;width:2.3rem;height:2.3rem;
        transform:translate(-50%,-50%) rotate(var(--a)) translateX(calc(var(--orbit) / 2)) rotate(calc(-1 * var(--a)))}
    .ce-satellite>span{display:flex;width:100%;height:100%;align-items:center;justify-content:center;border-radius:9999px;
        background:#fff;color:var(--g);border:1.5px solid rgba(249,168,37,.75);box-shadow:0 8px 16px -8px rgba(7,135,62,.5);
        animation:ce-pop .5s calc(.25s + var(--d) / 4) cubic-bezier(.34,1.56,.64,1) both,
                  ce-upright 24s linear infinite, ce-bob 3.6s var(--d) ease-in-out infinite}
    .ce-satellite svg{width:1.05rem;height:1.05rem;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
    .ce-core{position:relative;display:flex;align-items:center;justify-content:center;width:3.1rem;height:3.1rem;border-radius:9999px;
        background:var(--g);box-shadow:0 0 0 4px var(--gold),0 14px 26px -10px rgba(7,135,62,.7);
        animation:ce-pop .55s .1s cubic-bezier(.34,1.56,.64,1) both}
    .ce-core::after{content:"";position:absolute;inset:-4px;border-radius:9999px;border:2px solid var(--gold);animation:ce-ping 2.4s 1s ease-out infinite}
    .ce-core svg{width:1.4rem;height:1.4rem;fill:none;stroke:var(--gold);stroke-width:2.6;stroke-linecap:round}
    .ce-title{font-size:.95rem;font-weight:800;color:#05622d;line-height:1.3}
    .ce-text{margin-top:.3rem;max-width:17rem;font-size:.8rem;line-height:1.45;color:#6b7280}
    .ce-cta{margin-top:1rem;display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.15rem;border-radius:.8rem;
        background:var(--g);color:var(--gold);font-size:.85rem;font-weight:700;box-shadow:0 10px 20px -12px rgba(7,135,62,.8);
        transition:transform .2s ease,box-shadow .2s ease}
    .ce-cta svg{width:1rem;height:1rem;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;
        animation:ce-nudge 1.8s 1.2s ease-in-out infinite}
    .ce-cta:hover{transform:translateY(-1px);box-shadow:0 14px 24px -12px rgba(7,135,62,.85)}
    .ce-cta:active{transform:translateY(0) scale(.98)}
    @keyframes ce-rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
    @keyframes ce-spin{to{transform:rotate(360deg)}}
    @keyframes ce-upright{to{rotate:-360deg}}
    @keyframes ce-breathe{0%,100%{transform:scale(.9);opacity:.75}50%{transform:scale(1.08);opacity:1}}
    @keyframes ce-pop{from{transform:scale(0);opacity:0}to{transform:none;opacity:1}}
    @keyframes ce-bob{0%,100%{translate:0 0}50%{translate:0 -4px}}
    @keyframes ce-ping{0%{transform:scale(1);opacity:.9}80%,100%{transform:scale(1.55);opacity:0}}
    @keyframes ce-nudge{0%,60%,100%{translate:0 0}30%{translate:4px 0}}
    @media (prefers-reduced-motion:reduce){
        .ce-card,.ce-glow,.ce-ring,.ce-orbit,.ce-satellite>span,.ce-core,.ce-core::after,.ce-cta svg{animation:none!important}
        .ce-core::after{opacity:0}
    }
</style>
@endonce
