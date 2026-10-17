@extends('errors.layout')

@section('title', 'Sign In Required')
@section('code', '401')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
@endsection
@section('kicker', 'Sign in required')
@section('heading', 'Please log in to continue')

@section('message')
    <p class="lead">
        This part of ClubSync is only for signed-in students and staff of Saint
        Columban College. Log in with your school account and try again.
    </p>
@endsection

@section('actions')
    <a href="{{ route('login') }}" class="btn">Log in</a>
    <a href="{{ route('welcome') }}" class="btn-secondary">Back to the home page</a>
@endsection
