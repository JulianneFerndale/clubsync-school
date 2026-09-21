@extends('layouts.auth')
@section('title', 'Terms and Conditions — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="javascript:history.back()" class="text-green-800 hover:text-green-900 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Terms and Conditions</span>
    </div>

    <div class="flex-1 px-8 pb-12 space-y-6 text-sm text-gray-700 leading-relaxed">

        <p class="text-xs text-gray-400">Effective date: June 2025 · Saint Columban College, Pagadian City</p>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">1. Acceptance of Terms</h2>
            <p>By registering for and using ClubSync, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the platform.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">2. Eligibility</h2>
            <p>ClubSync is exclusively for currently enrolled students, faculty, and staff of Saint Columban College (SCC). You must use your institutional email address (<span class="font-medium">@sccpag.edu.ph</span>) to register.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">3. Account Responsibility</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You must not share your login details with others. Notify the system administrator immediately if you suspect unauthorized access.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">4. Acceptable Use</h2>
            <p>You agree to use ClubSync only for legitimate club-related activities. You must not:</p>
            <ul class="list-disc list-inside space-y-1 pl-2 text-gray-600">
                <li>Post offensive, harassing, or inappropriate content</li>
                <li>Impersonate other users or organizations</li>
                <li>Attempt to gain unauthorized access to any part of the system</li>
                <li>Use the platform for commercial purposes unrelated to SCC clubs</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">5. Data Privacy</h2>
            <p>ClubSync collects your name, institutional email, EDP number, department, course, and mobile number to facilitate club membership and notifications. This data is used solely within the platform and is not shared with third parties outside of SCC administration. By registering, you consent to this collection and use.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">6. AI-Assisted Content</h2>
            <p>Some announcements on ClubSync may be drafted with AI assistance. These are clearly labeled. AI-generated content is reviewed and approved by club officers and advisers before publication. ClubSync does not guarantee the accuracy of AI-assisted content.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">7. Club Membership</h2>
            <p>Joining a club through ClubSync is subject to approval by club officers. Membership may be revoked at any time by club officers or DSA. ClubSync records are supplementary to official club records maintained by the DSA office.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">8. Modifications</h2>
            <p>Saint Columban College reserves the right to update these Terms at any time. Continued use of ClubSync after changes are posted constitutes your acceptance of the revised Terms.</p>
        </section>

        <section class="space-y-2">
            <h2 class="font-bold text-green-900 text-base">9. Contact</h2>
            <p>For questions about these Terms, contact the DSA office or the ClubSync system administrator at Saint Columban College, Pagadian City.</p>
        </section>

        <div class="pt-4">
            <a href="javascript:history.back()"
               class="flex items-center justify-center w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 transition-colors">
                Back to Registration
            </a>
        </div>

    </div>
</div>
@endsection
