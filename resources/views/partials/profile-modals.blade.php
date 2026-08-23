@if ($canChangeAvatar || $canChangeBanner)
    <div class="report-modal profile-media-modal" data-profile-media-modal hidden>
        <div class="report-card profile-media-card" data-profile-media-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.profile.media_modal_title') }}">
            <div class="report-header">
                <div class="report-title" data-profile-media-title>{{ __('ui.profile.media_modal_title') }}</div>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.report.close') }}" data-profile-media-close>
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <div class="profile-media-info" data-profile-media-info></div>
            <div class="profile-media-editor" data-profile-media-editor hidden>
                <div class="profile-media-editor__frame" data-profile-media-frame>
                    <img class="profile-media-editor__image" data-profile-media-image alt="">
                </div>
                <label class="profile-media-editor__zoom">
                    <span>{{ __('ui.profile.media_zoom') }}</span>
                    <input class="input" type="range" min="1" max="3" step="0.01" value="1" data-profile-media-zoom>
                    <span class="profile-media-editor__zoom-value" data-profile-media-zoom-value>100%</span>
                </label>
            </div>
            <div class="form-error" data-profile-media-error hidden></div>
            <div class="profile-media-actions">
                <button type="button" class="ghost-btn ghost-btn--danger" data-profile-media-remove hidden>{{ __('ui.profile.media_remove') }}</button>
                <button type="button" class="ghost-btn" data-profile-media-choose>{{ __('ui.profile.media_choose') }}</button>
                <div class="profile-media-actions__spacer"></div>
                <button type="button" class="ghost-btn" data-profile-media-cancel>{{ __('ui.report.cancel') }}</button>
                <button type="button" class="submit-btn" data-profile-media-apply disabled>{{ __('ui.profile.media_apply') }}</button>
            </div>
            <div class="profile-media-loading" data-profile-media-loading hidden>
                <div class="profile-media-loading__spinner" aria-hidden="true"></div>
                <div class="profile-media-loading__text">{{ __('ui.profile.media_uploading') }}</div>
            </div>
        </div>
    </div>
@endif

@if (!empty($badges) || $canManageBadges)
    <div class="badge-modal badge-modal--view" data-badge-view-modal hidden>
        <div class="badge-view-card" data-badge-view-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.view_title') }}">
            <button type="button" class="icon-btn badge-view-card__close" data-badge-view-close aria-label="{{ __('ui.report.close') }}">
                <i data-lucide="x" class="icon"></i>
            </button>
            <div class="badge-view-card__media">
                <div class="badge-view-card__glow" aria-hidden="true"></div>
                <div class="badge-view-card__burst" data-badge-view-burst aria-hidden="true"></div>
                <img class="badge-view-card__icon" data-badge-view-icon alt="">
            </div>
            <div class="badge-view-card__body">
                <div class="badge-view-card__title" data-badge-view-label></div>
                <div class="badge-view-card__desc" data-badge-view-description></div>
                <div class="badge-view-card__meta">
                    <span class="badge-view-card__meta-label">{{ __('ui.badges.view_issued') }}</span>
                    <span class="badge-view-card__meta-value" data-badge-view-issued>{{ __('ui.badges.view_issued_unknown') }}</span>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($canManageBadges)
    <script type="application/json" data-badge-catalog nonce="{{ $csp_nonce ?? '' }}">@json($badge_catalog ?? [])</script>
    <script type="application/json" data-user-badges nonce="{{ $csp_nonce ?? '' }}">@json($badges)</script>

    <div class="badge-modal" data-badge-modal hidden>
        <div class="badge-card" data-badge-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.grant_title') }}">
            <div class="badge-header">
                <div>
                    <div class="badge-title">{{ __('ui.badges.grant_title') }}</div>
                    <div class="badge-subtitle">{{ __('ui.badges.grant_subtitle') }}</div>
                </div>
                <button type="button" class="icon-btn" data-badge-close aria-label="{{ __('ui.report.close') }}">
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <div class="badge-grid" data-badge-grid></div>
            <form class="badge-form" data-badge-form>
                <label>
                    {{ __('ui.badges.custom_name') }}
                    <input class="input" type="text" data-badge-name placeholder="{{ __('ui.badges.custom_name_placeholder') }}">
                </label>
                <label>
                    {{ __('ui.badges.custom_description') }}
                    <textarea class="input" rows="3" data-badge-description placeholder="{{ __('ui.badges.custom_description_placeholder') }}"></textarea>
                </label>
                <label>
                    {{ __('ui.badges.reason') }}
                    <input class="input" type="text" data-badge-reason placeholder="{{ __('ui.badges.reason_placeholder') }}">
                </label>
                <button type="submit" class="submit-btn" data-badge-submit>{{ __('ui.badges.grant_cta') }}</button>
            </form>
        </div>
    </div>

    <div class="badge-modal" data-badge-revoke-modal hidden>
        <div class="badge-card" data-badge-revoke-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.revoke_title') }}">
            <div class="badge-header">
                <div>
                    <div class="badge-title">{{ __('ui.badges.revoke_title') }}</div>
                    <div class="badge-subtitle">{{ __('ui.badges.revoke_subtitle') }}</div>
                </div>
                <button type="button" class="icon-btn" data-badge-revoke-close aria-label="{{ __('ui.report.close') }}">
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <div class="badge-revoke-list" data-badge-revoke-list></div>
            <div class="badge-revoke-empty" data-badge-revoke-empty hidden>{{ __('ui.badges.revoke_empty') }}</div>
        </div>
    </div>
@endif
