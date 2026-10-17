@props(['label' => 'Continue with Google', 'divider' => 'or'])
{{--
    "Continue with Google", for SCC Google Workspace accounts only. Renders nothing until
    the OAuth client is set up (config/google.php → sign_in), so the page shows exactly
    the sign-in methods that work.

    A full page load, never Turbo: the route redirects to accounts.google.com, which
    Turbo's fetch cannot follow, and a hover prefetch would start a sign-in nobody asked
    for. Styled to Google's sign-in button guidelines (white, grey outline, the "G").
--}}
@if (app(\App\Services\GoogleSignIn::class)->isConfigured())
    <div {{ $attributes }}>
        @if ($divider)
            <div class="flex items-center gap-3 my-6" aria-hidden="true">
                <span class="flex-1 h-px bg-gray-200"></span>
                <span class="text-xs text-gray-400">{{ $divider }}</span>
                <span class="flex-1 h-px bg-gray-200"></span>
            </div>
        @endif

        <a href="{{ route('auth.google') }}" data-turbo="false" data-google-sign-in
           class="flex items-center justify-center gap-3 w-full bg-white border border-[#747775] text-[#1F1F1F] font-medium text-[15px] py-3.5 px-6 rounded-full hover:bg-gray-50 focus:bg-gray-50 transition-colors">
            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            {{ $label }}
        </a>
        <p class="text-center text-xs text-gray-400 mt-2">SCC Google accounts only (@sccpag.edu.ph)</p>
    </div>
@endif
