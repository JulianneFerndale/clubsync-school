@extends('errors.layout')

@section('title', 'Server Error')
@section('code', '500')
@section('tone', 'red')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
@endsection
@section('kicker', 'Something went wrong')
@section('heading', 'ClubSync ran into a problem on our side')

{{-- Never show the exception message here: it can carry SQL, paths or other internals. --}}
@section('message')
    <p class="lead">
        This page hit an unexpected error while loading. It wasn't anything you did,
        and the details have been logged so it can be fixed.
    </p>
    <p>Try again in a moment. If it keeps happening, let your club adviser or the Office of Student Affairs know.</p>
@endsection
