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
@extends('errors.layout')

@section('title', 'Page Expired')
@section('code', '419')
@section('tone', 'amber')
@section('icon')
    {{-- A gentle clock, not a warning triangle: nothing has gone wrong. --}}
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 6v6l3.75 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
@endsection
@section('kicker', 'Panagdait')

@if ($stillSignedIn)
    @section('heading', 'That page rested a little too long')

    @section('message')
        <p class="lead">
            The form you just sent had been open long enough for its security code to
            lapse — so we held it back rather than risk sending it somewhere it
            shouldn't go. You're still signed in, and nothing was lost.
        </p>
        <p>Head back, refresh the page, and send it once more.</p>
    @endsection

    @section('actions')
        <button type="button" class="btn" onclick="history.back()">Go back and try again</button>
        <a href="{{ $homeUrl }}" class="btn-secondary">Return to my dashboard</a>
    @endsection
@else
    @section('heading', 'Welcome back — your session took a rest')

    @section('message')
        <p class="lead">
            You were away for a while, so ClubSync quietly signed you out to keep your
            account and your club's records safe. Nothing you've done was lost.
        </p>
        <p>Log in again and pick up right where you left off.</p>
    @endsection

    @section('actions')
        <a href="{{ route('login') }}" class="btn">Log in again</a>
        <a href="{{ route('welcome') }}" class="btn-secondary">Back to the home page</a>
    @endsection

    @section('note')
        For everyone's safety, ClubSync ends a session after
        {{ config('session.lifetime') }} minutes of quiet.
    @endsection
@endif
