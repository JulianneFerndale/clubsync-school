@php
    // ThrottleRequestsException carries the wait in its Retry-After header.
    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? (int) ($exception->getHeaders()['Retry-After'] ?? 0)
        : 0;
    $retryMinutes = (int) ceil($retryAfter / 60);
@endphp
@extends('errors.layout')

@section('title', 'Too Many Requests')
@section('code', '429')
@section('tone', 'amber')
@section('back-first', '1')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
@endsection
@section('kicker', 'A short pause')
@section('heading', "Let's take a quick breather")

@section('message')
    <p class="lead">
        There were a lot of requests in a short time, so ClubSync paused things for a
        moment to keep the site fair and safe for everyone.
    </p>
    @if ($retryAfter >= 60)
        <p>You can try again in about {{ $retryMinutes }} {{ str('minute')->plural($retryMinutes) }}.</p>
    @elseif ($retryAfter > 0)
        <p>You can try again in about {{ $retryAfter }} {{ str('second')->plural($retryAfter) }}.</p>
    @else
        <p>Wait a minute, then try again.</p>
    @endif
@endsection
