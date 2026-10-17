@extends('errors.layout')

@section('title', 'Page Not Found')
@section('code', '404')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
@endsection
@section('kicker', 'Page not found')
@section('heading', "We couldn't find that page")

{{-- The exception message is deliberately not shown: for a missing record it
     names the model class ("No query results for model [App\Models\Club]"). --}}
@section('message')
    <p class="lead">
        The link may be old or mistyped, or what it pointed to — a club, an activity,
        an announcement — may have been moved or removed.
    </p>
    <p>Check the address, or head back and find it from your dashboard.</p>
@endsection
