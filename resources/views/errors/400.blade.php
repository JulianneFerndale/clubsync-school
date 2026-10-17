@extends('errors.layout')

@section('title', 'Bad Request')
@section('code', '400')
@section('tone', 'amber')
@section('back-first', '1')
@section('kicker', 'Something got mixed up')
@section('heading', "That request didn't come through right")

@section('message')
    <p class="lead">
        Part of what your browser sent was incomplete or garbled, so ClubSync
        couldn't make sense of it. Nothing was changed.
    </p>
    <p>Going back and trying once more usually clears it up.</p>
@endsection
