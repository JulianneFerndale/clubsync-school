@extends('layouts.app-member')
@section('title', 'Notifications — ClubSync')
@section('live-refresh', 'any')

@section('content')
    @include('notifications.partials.feed')
@endsection
