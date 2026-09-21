@extends($layout)
@section('title', 'Notifications — ClubSync')
@section('page-title', 'Notifications')
@section('club-name', 'ClubSync')
@section('live-refresh', 'any')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('notifications.partials.feed')
</div>
@endsection
