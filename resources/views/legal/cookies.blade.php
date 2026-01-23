@extends('layouts.app')

@section('title', 'Cookie Policy — Waasabi')
@section('page', 'legal-cookies')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/COOKIE_POLICY.md'])
@endsection
