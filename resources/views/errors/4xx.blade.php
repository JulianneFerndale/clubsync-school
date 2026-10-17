@php
    $status = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400;
@endphp
@extends('errors.layout')

{{-- Fallback for any 4xx status without a page of its own (Laravel looks for
     errors/{code} first, then errors/4xx). --}}
@section('title', \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Request Error')
@section('code', (string) $status)
@section('tone', 'amber')
@section('back-first', '1')
@section('kicker', "Couldn't complete that")
@section('heading', "That request couldn't be completed")

@section('message')
    <p class="lead">
        ClubSync couldn't finish what was asked, and nothing was changed.
    </p>
    <p>Go back and try once more, or return to your dashboard.</p>
@endsection
