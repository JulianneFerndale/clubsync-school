@component('mail::message')
# {{ $announcement->title ?? 'New announcement' }}

**{{ $clubName }}** posted a new announcement on ClubSync.

@if($announcement->content)
{{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 600) }}
@endif

@if($url)
@component('mail::button', ['url' => $url, 'color' => 'success'])
Read on ClubSync
@endcomponent
@endif

You are receiving this because you are a member of {{ $clubName }}.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
