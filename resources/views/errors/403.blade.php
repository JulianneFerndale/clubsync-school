@php
    // abort(403, '…') messages in ClubSync are written for the user ("Only the club
    // president can submit reports to the DSA."), so show them. Laravel's stock
    // wording adds nothing to the page, so it is left out.
    $reason  = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $generic = ['', 'Forbidden', 'Unauthorized', 'Unauthorized.', 'This action is unauthorized.'];
@endphp
@extends('errors.layout')

@section('title', 'Access Restricted')
@section('code', '403')
@section('tone', 'amber')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
@endsection
@section('kicker', 'Access restricted')
@section('heading', "This page isn't open to your account")

@section('message')
    <p class="lead">
        ClubSync shows each person the pages that match their role — students, club
        officers, advisers and the Office of Student Affairs each have their own.
        This one belongs to a different role or club.
    </p>
    @unless (in_array($reason, $generic, true))
        <div class="detail" role="note">{{ $reason }}</div>
    @endunless
@endsection

@section('note', 'If you believe you should have access, ask your club adviser or the Director of Student Affairs.')
