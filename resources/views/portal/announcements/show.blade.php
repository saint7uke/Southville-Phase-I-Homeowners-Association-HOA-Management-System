@extends('layouts.portal')
@section('title', $announcement->title)
@section('content')
<article class="panel"><p><a href="{{ route('portal.announcements.index') }}">Back to announcements</a></p><span class="category">{{ $announcement->category }}</span><h1>{{ $announcement->title }}</h1><p><time datetime="{{ $announcement->published_at->toIso8601String() }}">Published {{ $announcement->published_at->format('M d, Y g:i A') }}</time></p><div style="white-space:pre-line;max-width:70ch">{{ $announcement->content }}</div></article>
@endsection
