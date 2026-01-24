@extends('layouts.app')

@section('title', 'Terms of Service — Waasabi')
@section('page', 'legal-terms')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/TERMS_OF_SERVICE.md'])
@endsection
