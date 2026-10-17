@php
    $status = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
@endphp
@extends('errors.layout')

{{-- Fallback for any 5xx status without a page of its own. --}}
@section('title', \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Server Error')
@section('code', (string) $status)
@section('tone', 'red')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
@endsection
@section('kicker', 'Something went wrong')
@section('heading', 'ClubSync is having trouble right now')

@section('message')
    <p class="lead">
        The server couldn't finish loading this page. It wasn't anything you did.
    </p>
    <p>Try again in a moment. If it keeps happening, let your club adviser or the Office of Student Affairs know.</p>
@endsection
