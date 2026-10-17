@extends($layout)
@section('title', 'Notifications — ClubSync')
@section('page-title', 'Notifications')
@section('club-name', 'ClubSync')
@section('live-refresh', 'any')

@section('content')
    @include('notifications.partials.feed')
@endsection
