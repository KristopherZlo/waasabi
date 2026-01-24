@extends('layouts.app')

@section('title', 'Privacy Policy — Waasabi')
@section('page', 'legal-privacy')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/PRIVACY_POLICY.md'])
@endsection
