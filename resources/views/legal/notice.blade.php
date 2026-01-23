@extends('layouts.app')

@section('title', 'Notice & Action — Waasabi')
@section('page', 'legal-notice')

@section('content')
    @include('legal._markdown', ['path' => 'docs/legal/published/NOTICE_AND_ACTION.md'])
@endsection
