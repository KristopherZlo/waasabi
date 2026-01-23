@extends('layouts.app')

@section('title', 'Community Guidelines — Waasabi')
@section('page', 'legal-guidelines')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/COMMUNITY_GUIDELINES.md'])
@endsection
