import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';
import { submitReport } from './report';
import { requestModerationReason, resolveModerationReasonTitle } from './moderation-reason';

let adminToggleBound = false;

export const setupAdminModeToggle = () => {
    const button = document.querySelector<HTMLButtonElement>('[data-admin-toggle]');
    if (!button) {
        return;
    }
    if (adminToggleBound) {
        return;
    }
    if (button.dataset.adminToggleBound === '1') {
        return;
    }
    button.dataset.adminToggleBound = '1';
    adminToggleBound = true;
    const key = 'adminEditMode';
    const apply = (enabled: boolean) => {
        document.body.classList.toggle('is-admin-edit', enabled);
        button.classList.toggle('is-active', enabled);
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    };
    apply(localStorage.getItem(key) === '1');

    button.addEventListener('click', () => {
        const enabled = !document.body.classList.contains('is-admin-edit');
        localStorage.setItem(key, enabled ? '1' : '0');
        apply(enabled);
        toast.show(enabled ? t('admin_edit_on', 'Edit mode on.') : t('admin_edit_off', 'Edit mode off.'));
    });
};

export const setupAdminControls = () => {
    const deleteButtons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-admin-delete]'));
    const flagButtons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-admin-flag]'));
    const moderationButtons = Array.from(
        document.querySelectorAll<HTMLButtonElement>(
            '[data-admin-queue], [data-admin-hide], [data-admin-restore], [data-admin-nsfw]',
        ),
    );
    if (!deleteButtons.length && !flagButtons.length && !moderationButtons.length) {
        return;
    }

    const isEditMode = () => document.body.dataset.page === 'admin' || document.body.classList.contains('is-admin-edit');
    const moderationLabel = (status: string) => t(`moderation_status_${status}`, status);

    const toggleModerationButtons = (scope: HTMLElement, status: string) => {
        const queueButtons = scope.querySelectorAll<HTMLButtonElement>('[data-admin-queue]');
        const hideButtons = scope.querySelectorAll<HTMLButtonElement>('[data-admin-hide]');
        const restoreButtons = scope.querySelectorAll<HTMLButtonElement>('[data-admin-restore]');
        queueButtons.forEach((button) => {
            button.hidden = status === 'pending';
        });
        hideButtons.forEach((button) => {
            button.hidden = status === 'hidden';
        });
        restoreButtons.forEach((button) => {
            button.hidden = status === 'approved';
        });
    };

    const updateModerationScope = (button: HTMLButtonElement, status: string) => {
        const scope = button.closest<HTMLElement>('[data-moderation-scope]');
        if (!scope) {
            return;
        }
        const normalized = status || scope.dataset.moderationStatus || 'approved';
        scope.dataset.moderationStatus = normalized;
        toggleModerationButtons(scope, normalized);

        const chipContainer = scope.querySelector<HTMLElement>(
            '.post-tags, .article-tags, .question-page__tags, .comment-meta, .admin-moderation__meta',
        );
        if (!chipContainer) {
            return;
        }
        const existingChip = scope.querySelector<HTMLElement>('.chip--moderation');
        if (normalized === 'approved') {
            existingChip?.remove();
            return;
        }
        const chip = existingChip ?? document.createElement('span');
        chip.className = `chip chip--moderation chip--${normalized}`;
        chip.textContent = moderationLabel(normalized);
        if (!existingChip) {
            chipContainer.appendChild(chip);
        }
    };

    deleteButtons.forEach((button) => {
        if (button.dataset.adminDeleteBound === '1') {
            return;
        }
        button.dataset.adminDeleteBound = '1';
        button.addEventListener('click', async () => {
            if (!isEditMode()) {
                return;
            }
            const url = button.dataset.adminUrl ?? '';
            if (!url) {
                return;
            }
            const reason = await requestModerationReason({
                title: resolveModerationReasonTitle('delete'),
                placeholder: t('moderation_reason_placeholder', 'Explain why this action is needed.'),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            });
            if (!reason) {
                return;
            }
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: new URLSearchParams({
                        _method: 'DELETE',
                        _token: csrfToken,
                        reason,
                    }).toString(),
                });
                if (response.status === 422) {
                    toast.show(t('moderation_reason_required', 'Reason is required.'));
                    return;
                }
                if (!response.ok) {
                    toast.show(t('admin_delete_failed', 'Delete failed.'));
                    return;
                }
                const card = button.closest<HTMLElement>('[data-feed-card]');
                const comment = button.closest<HTMLElement>('.comment');
                if (card) {
                    card.remove();
                } else if (comment) {
                    comment.remove();
                } else if (['project', 'question'].includes(document.body.dataset.page ?? '')) {
                    window.location.href = appUrl || '/';
                }
                toast.show(t('admin_delete_done', 'Deleted.'));
            } catch {
                toast.show(t('admin_delete_failed', 'Delete failed.'));
            }
        });
    });

    moderationButtons.forEach((button) => {
        if (button.dataset.adminModerationBound === '1') {
            return;
        }
        button.dataset.adminModerationBound = '1';
        const action = button.hasAttribute('data-admin-queue')
            ? 'queue'
            : button.hasAttribute('data-admin-hide')
              ? 'hide'
              : button.hasAttribute('data-admin-nsfw')
                ? 'nsfw'
                : 'restore';
        button.addEventListener('click', async () => {
            if (!isEditMode()) {
                return;
            }
            const url = button.dataset.adminUrl ?? '';
            if (!url) {
                return;
            }
            const needsReason = action === 'queue' || action === 'hide';
            const reason = needsReason
                ? await requestModerationReason({
                      title: resolveModerationReasonTitle(action),
                      placeholder: t('moderation_reason_placeholder', 'Explain why this action is needed.'),
                      submitLabel: t('moderation_reason_submit', 'Confirm'),
                  })
                : null;
            if (needsReason && !reason) {
                return;
            }
            button.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        ...(needsReason
                            ? { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
                            : {}),
                    },
                    body: needsReason
                        ? new URLSearchParams({
                              _token: csrfToken,
                              reason: reason ?? '',
                          }).toString()
                        : undefined,
                });
                if (response.status === 401) {
                    window.location.href = `${appUrl}/login`;
                    return;
                }
                if (response.status === 403) {
                    toast.show(t('admin_action_forbidden', 'Not allowed.'));
                    return;
                }
                if (response.status === 422) {
                    toast.show(t('moderation_reason_required', 'Reason is required.'));
                    return;
                }
                if (!response.ok) {
                    toast.show(t('admin_action_failed', 'Moderation action failed.'));
                    return;
                }
                const data = (await response.json()) as { status?: string };
                const nextStatus = typeof data.status === 'string' ? data.status.toLowerCase() : '';
                updateModerationScope(button, nextStatus);
                if (action === 'nsfw') {
                    button.hidden = true;
                }
                const messageKey =
                    action === 'queue'
                        ? 'admin_queue_done'
                        : action === 'hide'
                          ? 'admin_hide_done'
                          : action === 'nsfw'
                            ? 'admin_nsfw_done'
                            : 'admin_restore_done';
                const fallback =
                    action === 'queue'
                        ? 'Queued for moderation.'
                        : action === 'hide'
                          ? 'Hidden.'
                          : action === 'nsfw'
                            ? 'Marked NSFW.'
                            : 'Restored.';
                toast.show(t(messageKey, fallback));
            } catch {
                toast.show(t('admin_action_failed', 'Moderation action failed.'));
            } finally {
                button.disabled = false;
            }
        });
    });

    flagButtons.forEach((button) => {
        if (button.dataset.adminFlagBound === '1') {
            return;
        }
        button.dataset.adminFlagBound = '1';
        button.addEventListener('click', async () => {
            if (!isEditMode()) {
                return;
            }
            const payload = {
                content_type: button.dataset.reportType ?? 'content',
                content_id: button.dataset.reportId ?? null,
                content_url: button.dataset.reportUrl ?? window.location.href,
                reason: 'admin_flag',
                details: 'Flagged by admin',
            };
            const ok = await submitReport(payload);
            toast.show(ok ? t('admin_flagged', 'Flagged.') : t('report_failed', 'Unable to send report.'));
        });
    });
};

export const setupModerationReasonForms = () => {
    const forms = Array.from(document.querySelectorAll<HTMLFormElement>('[data-moderation-reason-form]'));
    if (!forms.length) {
        return;
    }
    forms.forEach((form) => {
        if (form.dataset.moderationReasonBound === '1') {
            return;
        }
        form.dataset.moderationReasonBound = '1';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const action = form.dataset.moderationAction ?? 'moderation';
            const reason = await requestModerationReason({
                title: resolveModerationReasonTitle(action),
                placeholder: t('moderation_reason_placeholder', 'Explain why this action is needed.'),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            });
            if (!reason) {
                return;
            }
            let reasonInput = form.querySelector<HTMLInputElement>('input[name="reason"]');
            if (!reasonInput) {
                reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'reason';
                form.appendChild(reasonInput);
            }
            reasonInput.value = reason;
            form.submit();
        });
    });
};

export const setupConfirmActions = () => {
    const forms = Array.from(document.querySelectorAll<HTMLFormElement>('[data-confirm-submit]'));
    forms.forEach((form) => {
        if (form.dataset.confirmBound === '1') {
            return;
        }
        form.dataset.confirmBound = '1';
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmMessage ?? t('confirm_action', 'Are you sure?');
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const selects = Array.from(document.querySelectorAll<HTMLSelectElement>('[data-confirm-select]'));
    selects.forEach((select) => {
        if (select.dataset.confirmBound === '1') {
            return;
        }
        select.dataset.confirmBound = '1';
        select.dataset.confirmPrev = select.value;
        select.addEventListener('focus', () => {
            select.dataset.confirmPrev = select.value;
        });
        select.addEventListener('change', () => {
            const message = select.dataset.confirmMessage ?? t('confirm_action', 'Are you sure?');
            if (!window.confirm(message)) {
                select.value = select.dataset.confirmPrev ?? select.value;
                return;
            }
            const form = select.closest<HTMLFormElement>('form');
            form?.submit();
        });
    });
};

export const setupAuthorActions = () => {
    const buttons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-author-delete]'));
    if (!buttons.length) {
        return;
    }
    buttons.forEach((button) => {
        if (button.dataset.authorDeleteBound === '1') {
            return;
        }
        button.dataset.authorDeleteBound = '1';
        button.addEventListener('click', async () => {
            const url = button.dataset.authorDeleteUrl ?? '';
            if (!url) {
                return;
            }
            const confirmed = window.confirm(t('author_delete_confirm', 'Delete this post?'));
            if (!confirmed) {
                return;
            }
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: new URLSearchParams({
                        _method: 'DELETE',
                        _token: csrfToken,
                    }).toString(),
                });
                if (!response.ok) {
                    toast.show(t('admin_delete_failed', 'Delete failed.'));
                    return;
                }
                const card = button.closest<HTMLElement>('[data-feed-card]');
                if (card) {
                    card.remove();
                } else {
                    window.location.href = (button.dataset.authorDeleteRedirect ?? appUrl) || '/';
                }
                toast.show(t('admin_delete_done', 'Deleted.'));
            } catch {
                toast.show(t('admin_delete_failed', 'Delete failed.'));
            }
        });
    });
};
