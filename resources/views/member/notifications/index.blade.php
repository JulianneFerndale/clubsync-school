@extends('layouts.app-member')
@section('title', 'Notifications — ClubSync')
@section('page-title', 'Notifications')
@section('live-refresh', 'any')

@section('content')
    @include('notifications.partials.feed')
@endsection
