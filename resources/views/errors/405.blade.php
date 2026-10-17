@extends('errors.layout')

@section('title', 'Method Not Allowed')
@section('code', '405')
@section('tone', 'amber')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
@endsection
@section('kicker', 'Wrong way in')
@section('heading', 'That address only works from a form')

@section('message')
    <p class="lead">
        You reached a link ClubSync only uses when a form is sent — this often happens
        after refreshing or reopening a page right after submitting something.
        Nothing was changed.
    </p>
    <p>Head back to the page you were on and continue from there.</p>
@endsection
