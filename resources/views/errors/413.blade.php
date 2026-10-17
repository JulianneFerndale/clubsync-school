@extends('errors.layout')

@section('title', 'File Too Large')
@section('code', '413')
@section('tone', 'amber')
@section('back-first', '1')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
@endsection
@section('kicker', 'Upload too large')
@section('heading', 'That file is too big to upload')

@section('message')
    <p class="lead">
        The upload was larger than ClubSync accepts, so nothing was saved.
    </p>
    <p>
        Go back and choose a smaller file — a photo taken at a lower size, or a PDF
        within the limit shown beside the upload field.
    </p>
@endsection
