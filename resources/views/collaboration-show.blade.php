@extends('layouts.app')

@section('title', $collaboration_request->title)
@section('description', $collaboration_request->summary)
@section('page', 'collaboration-opening')

@section('content')
    @php
        $opening = $collaboration_request;
        $roleLabel = $collaboration_roles[$opening->role] ?? $opening->role;
        $myApplication = Auth::check() && ! $is_manager ? $opening->applications->first(fn ($a) => ! in_array($a->status, ['withdrawn', 'closed'], true)) : null;
        $statusKey = $opening->isOpen() ? 'open' : ($opening->status === 'open' ? 'closed' : $opening->status);
    @endphp
    <div class="collaboration-opening-page">
        <a class="collaboration-opening-page__back" href="{{ route('feed', ['stream' => 'collaboration']) }}">
            <i data-lucide="arrow-left" class="icon"></i>
            <span>{{ __('ui.collaboration.back_to_openings') }}</span>
        </a>

        <div class="collaboration-opening-layout">
            <article class="collaboration-opening-detail">
                <header class="collaboration-opening-detail__header">
                    <div class="collaboration-opening-detail__status">
                        <span class="collaboration-status collaboration-status--{{ $statusKey }}">{{ __('ui.collaboration.status_'.$statusKey) }}</span>
                        <span>{{ __('ui.collaboration.posted_time', ['time' => $opening->created_at->diffForHumans()]) }}</span>
                    </div>
                    <h1>{{ $opening->title }}</h1>
                    <div class="collaboration-opening-detail__owner">
@if ($opening->post)
                        <a href="{{ route('project', $opening->post->slug) }}">{{ $opening->post->title }}</a>
@endif
                        <span aria-hidden="true">&middot;</span>
                        <a href="{{ route('profile.show', $opening->user->slug) }}">{{ $opening->user->name }}</a>
                    </div>
                    <p class="collaboration-opening-detail__deadline">
                        {{ $opening->expires_at ? __('ui.collaboration.deadline_date', ['date' => $opening->expires_at->translatedFormat('j M Y')]) : __('ui.collaboration.no_deadline') }}
                    </p>
                    @if (! $is_manager && $opening->isOpen() && ! $myApplication)
                        <a class="cta-btn collaboration-opening-detail__primary-action" href="#application">{{ __('ui.collaboration.apply') }}</a>
                    @elseif ($is_manager && $opening->applications_count > 0)
                        <a class="cta-btn collaboration-opening-detail__primary-action" href="#applications">{{ __('ui.collaboration.view_applications') }}</a>
                    @endif
                    @auth
                        @if (Auth::id() !== $opening->user_id)
                            <button class="ghost-btn ghost-btn--compact" type="button"
                                data-report-open data-report-type="collaboration" data-report-id="{{ $opening->id }}"
                                data-report-url="{{ url()->current() }}">
                                <i data-lucide="flag" class="icon"></i>
                                {{ __('ui.report.flag') }}
                            </button>
                        @endif
                    @endauth
                </header>

                <section class="collaboration-opening-detail__section">
                    <h2>{{ __('ui.collaboration.position_details') }}</h2>
                    <p>{{ $opening->summary }}</p><p class="helper">{{ __('waasabi.try_note') }}</p>
                </section>

                @if ($opening->skills)
                    <section class="collaboration-opening-detail__section">
                        <h2>{{ __('ui.collaboration.skills_heading') }}</h2>
                        <div class="collaboration-opening-detail__skills">
                            @foreach ($opening->skills as $skill)
                                <span>{{ $skill }}</span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @unless ($is_manager)
                    <section class="collaboration-opening-detail__section collaboration-response-panel" id="application">
                    @if ($myApplication)
                        <h2>{{ __('ui.collaboration.your_application') }}</h2>
                        <div class="collaboration-application-state">
                            <strong>{{ __('ui.collaboration.application_'.$myApplication->status) }}</strong>
                            <p>{{ $myApplication->message }}</p>
                        </div>
                        @if (in_array($myApplication->status, ['pending', 'accepted'], true))
                            <form method="POST" action="{{ route('collaboration.applications.withdraw', $myApplication) }}">
                                @csrf
                                @method('DELETE')
                                <button class="ghost-btn" type="submit">{{ $myApplication->status === 'accepted' ? __('waasabi.leave') : __('ui.collaboration.withdraw') }}</button>
                            </form>
                        @endif
                    @elseif ($opening->isOpen())
                        <h2>{{ __('ui.collaboration.apply_title') }}</h2>
                        <p class="collaboration-response-panel__intro">{{ __('waasabi.apply_hint') }}</p>
                        @guest
                            <a class="cta-btn" href="{{ route('login') }}">{{ __('ui.collaboration.login_to_apply') }}</a>
                        @else
                            @error('message')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                            @error('applicant_post_id')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                            <form class="collaboration-application-form" method="POST" action="{{ route('collaboration.applications.store', $opening) }}">
                                @csrf
                                <div class="honeypot-field" aria-hidden="true">
                                    <label>{{ __('ui.auth.website') }}<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                                </div>
                                <label>
                                    <span class="label-text">{{ __('waasabi.apply_optional') }}</span>
                                    <textarea class="input" name="message" rows="3" maxlength="1200">{{ old('message') }}</textarea>
                                </label>
                                @if ($manageable_projects->where('id', '!=', $opening->post_id)->isNotEmpty())
                                    <label>
                                        <span class="label-text">{{ __('ui.collaboration.application_project') }}</span>
                                        <select class="input" name="applicant_post_id">
                                            <option value="">{{ __('ui.collaboration.application_project_none') }}</option>
                                            @foreach ($manageable_projects as $project)
                                                @if ($project->id !== $opening->post_id)
                                                    <option value="{{ $project->id }}" @selected((int) old('applicant_post_id') === $project->id)>{{ $project->title }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                <button class="cta-btn" type="submit">{{ __('waasabi.join') }}</button>
                            </form>
                        @endguest
                    @else
                        <h2>{{ __('ui.collaboration.opening_unavailable') }}</h2>
                        <p class="collaboration-response-panel__intro">{{ __('ui.collaboration.opening_unavailable_helper') }}</p>
                    @endif
                    </section>
                @endunless

                @if ($is_manager)
                    <section class="collaboration-candidates" id="applications">
                        <div class="collaboration-candidates__heading">
                            <h2>{{ __('ui.collaboration.applications') }}</h2>
                            <span>{{ trans_choice('ui.collaboration.applications_count', $opening->applications_count, ['count' => $opening->applications_count]) }}</span>
                        </div>
                        @forelse ($opening->applications as $application)
                            <article class="collaboration-candidate">
                                <header>
                                    <a href="{{ route('profile.show', $application->user->slug) }}">{{ $application->user->name }}</a>
                                    <span>{{ __('ui.collaboration.application_'.$application->status) }}</span>
                                </header>
                                <p>{{ $application->message }}</p>
                                @if ($application->applicantPost)
                                    <a href="{{ route('project', $application->applicantPost->slug) }}">{{ __('ui.collaboration.application_project') }}: {{ $application->applicantPost->title }}</a>
                                @endif
                                @if ($application->status === 'pending' && $opening->isOpen())
                                    <div class="collaboration-candidate__actions">
                                        <form method="POST" action="{{ route('collaboration.applications.decide', $application) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="accepted">
                                            <button class="cta-btn" type="submit">{{ __('ui.collaboration.accept') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('collaboration.applications.decide', $application) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="ghost-btn" type="submit">{{ __('ui.collaboration.reject') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="collaboration-candidates__empty">{{ __('ui.collaboration.applications_empty') }}</p>
                        @endforelse
                    </section>
                @endif
            </article>

            <aside class="collaboration-opening-sidebar">
                <section class="collaboration-opening-facts">
                    <h2>{{ __('ui.collaboration.basic_info') }}</h2>
                    <dl>
                        <div>
                            <dt>{{ __('ui.collaboration.form_role') }}</dt>
                            <dd>{{ $roleLabel }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.collaboration.form_availability') }}</dt>
                            <dd>{{ $collaboration_availability[$opening->availability] ?? $opening->availability }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.collaboration.form_format') }}</dt>
                            <dd>{{ $collaboration_formats[$opening->format] ?? $opening->format }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.collaboration.deadline') }}</dt>
                            <dd>{{ $opening->expires_at ? $opening->expires_at->translatedFormat('j M Y') : __('ui.collaboration.no_deadline') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.collaboration.about_project') }}</dt>
@if ($opening->post)
                            <dd><a href="{{ route('project', $opening->post->slug) }}">{{ $opening->post->title }}</a></dd>
@endif
                        </div>
                    </dl>
                </section>

                @if ($is_manager)
                    <section class="collaboration-application-panel" id="application">
                        <h2>{{ __('ui.collaboration.manage_opening') }}</h2>
                        <p>{{ trans_choice('ui.collaboration.applications_count', $opening->applications_count, ['count' => $opening->applications_count]) }}</p>
                        <div class="collaboration-application-panel__actions">
                            @if ($opening->applications_count > 0)
                                <a class="cta-btn" href="#applications">{{ __('ui.collaboration.view_applications') }}</a>
                            @endif
                            <form method="POST" action="{{ route('collaboration.status', $opening) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $opening->status === 'open' ? 'closed' : 'open' }}">
                                <button class="ghost-btn" type="submit">{{ $opening->status === 'open' ? __('ui.collaboration.close') : __('ui.collaboration.reopen') }}</button>
                            </form>
                            <form method="POST" action="{{ route('collaboration.destroy', $opening) }}" data-confirm-submit data-confirm-message="{{ __('ui.collaboration.delete_confirm') }}">
                                @csrf
                                @method('DELETE')
                                <button class="ghost-btn ghost-btn--danger" type="submit">{{ __('ui.collaboration.delete') }}</button>
                            </form>
                        </div>
                    </section>
                @endif
            </aside>
        </div>

        <section class="collaboration-comments" id="comments">
            <header class="collaboration-comments__header">
                <h2>{{ __('ui.collaboration.comments') }}</h2>
                <span>{{ trans_choice('ui.collaboration.comments_count', $opening->comments_count, ['count' => $opening->comments_count]) }}</span>
            </header>

            @auth
                <form class="collaboration-comment-form" method="POST" action="{{ route('collaboration.comments.store', $opening) }}">
                    @csrf
                    <label class="sr-only" for="collaboration-comment-body">{{ __('ui.collaboration.comment_placeholder') }}</label>
                    <textarea class="input" id="collaboration-comment-body" name="body" rows="3" maxlength="2000" placeholder="{{ __('ui.collaboration.comment_placeholder') }}" required>{{ old('body') }}</textarea>
                    @error('body')
                        <div class="form-error" role="alert">{{ $message }}</div>
                    @enderror
                    <button class="cta-btn" type="submit">{{ __('ui.collaboration.comment_submit') }}</button>
                </form>
            @else
                <p class="collaboration-comments__login"><a href="{{ route('login') }}">{{ __('ui.collaboration.comment_login') }}</a></p>
            @endauth

            <div class="collaboration-comment-list">
                @forelse ($opening->comments as $comment)
                    <article class="collaboration-comment" id="comment-{{ $comment->id }}">
                        <img src="{{ $comment->user->avatar ?: '/images/avatar-default.svg' }}" alt="" loading="lazy">
                        <div class="collaboration-comment__content">
                            <header>
                                <a href="{{ route('profile.show', $comment->user->slug) }}">{{ $comment->user->name }}</a>
                                @if ($comment->user_id === $opening->user_id)
                                    <span>{{ __('ui.collaboration.comment_author') }}</span>
                                @endif
                                <time datetime="{{ $comment->created_at->toAtomString() }}">{{ $comment->created_at->diffForHumans() }}</time>
                            </header>
                            <p>{{ $comment->body }}</p>
                        </div>
                        @auth
                            @if (Auth::id() === $comment->user_id || Auth::user()->hasRole('moderator'))
                                <form method="POST" action="{{ route('collaboration.comments.destroy', $comment) }}" data-confirm-submit data-confirm-message="{{ __('ui.collaboration.comment_delete_confirm') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-btn" type="submit" title="{{ __('ui.collaboration.comment_delete') }}" aria-label="{{ __('ui.collaboration.comment_delete') }}">
                                        <i data-lucide="trash-2" class="icon"></i>
                                    </button>
                                </form>
                            @endif
                            @if (Auth::id() !== $comment->user_id)
                                <button class="icon-btn" type="button" title="{{ __('ui.report.flag') }}" aria-label="{{ __('ui.report.flag') }}"
                                    data-report-open data-report-type="collaboration_comment" data-report-id="{{ $comment->id }}"
                                    data-report-url="{{ url()->current() }}#comment-{{ $comment->id }}">
                                    <i data-lucide="flag" class="icon"></i>
                                </button>
                            @endif
                        @endauth
                    </article>
                @empty
                    <p class="collaboration-comments__empty">{{ __('ui.collaboration.comments_empty') }}</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
