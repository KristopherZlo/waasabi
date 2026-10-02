@extends('layouts.static')

@section('context-nav')
    @php($supportTab = in_array(request('tab'), ['home', 'tickets', 'new'], true) ? request('tab') : 'home')
    <nav class="support-nav" aria-label="{{ __('ui.support.title') }}">
        <a class="{{ $supportTab === 'home' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'home']) }}">{{ __('ui.support.portal_nav_home') }}</a>
        <a class="{{ $supportTab === 'tickets' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'tickets']) }}">{{ __('ui.support.portal_nav_tickets') }}</a>
        <a class="{{ $supportTab === 'new' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'new']) }}">{{ __('ui.support.portal_nav_new') }}</a>
    </nav>
@endsection

@push('scripts')
<script nonce="{{ $csp_nonce ?? '' }}">
document.querySelectorAll('[data-support-search]').forEach(input => input.addEventListener('input', event => {
    const term = event.currentTarget.value.trim().toLowerCase();
    document.querySelectorAll('[data-support-item]').forEach(item => item.hidden = term !== '' && !(item.dataset.supportSearch || item.textContent).toLowerCase().includes(term));
    document.querySelectorAll('[data-support-group]').forEach(group => group.hidden = !group.querySelector('[data-support-item]:not([hidden])'));
}));
</script>
@endpush
