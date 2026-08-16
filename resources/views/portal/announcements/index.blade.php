@extends('layouts.portal')
@section('title', 'Announcements')
@section('content')
<header class="page-header"><h1>Announcements</h1><p>Verified notices published by the Southville Phase I HOA.</p></header>
<section class="panel announcement-list">@forelse($announcements as $item)<article><div><span class="category">{{ $item->category }}</span><time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->format('M d, Y') }}</time></div><h2><a href="{{ route('portal.announcements.show', $item) }}">{{ $item->title }}</a></h2><p>{{ Str::limit($item->content, 220) }}</p></article>@empty<p>No announcements have been published.</p>@endforelse</section>{{ $announcements->links() }}
@endsection
