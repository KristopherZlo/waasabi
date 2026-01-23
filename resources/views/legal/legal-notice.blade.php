@extends('layouts.app')

@section('title', 'Legal Notice — Waasabi')
@section('page', 'legal-legal-notice')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/LEGAL_NOTICE.md'])
@endsection
