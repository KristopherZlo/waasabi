import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';
import { submitReport } from './report';
import {
    requestModerationConfirmation,
    requestModerationReason,
    resolveModerationConfirmationTitle,
    resolveModerationReasonTitle,
} from './moderation-reason';

let adminToggleBound = false;
let adminSearchShortcutBound = false;
let adminActionMenusBound = false;
let pendingActionTimer: number | null = null;

const hasPendingAction = () => pendingActionTimer !== null;

const scheduleUndoableAction = (commit: () => void | Promise<void>, cancel: () => void = () => {}) => {
    if (hasPendingAction()) {
        return false;
    }
    toast.show(t('admin_action_pending', 'Action will be applied in 5 seconds.'), {
        actionLabel: t('undo', 'Undo'),
        duration: 5000,
        onAction: () => {
            if (pendingActionTimer !== null) {
                window.clearTimeout(pendingActionTimer);
                pendingActionTimer = null;
            }
            cancel();
            toast.show(t('admin_action_cancelled', 'Action canceled.'));
        },
    });
    pendingActionTimer = window.setTimeout(() => {
        pendingActionTimer = null;
        void commit();
    }, 5000);
    return true;
};

const submitForm = (form: HTMLFormElement, submitter: HTMLButtonElement | HTMLInputElement | null = null) => {
    if (submitter?.name) {
        const value = document.createElement('input');
        value.type = 'hidden';
        value.name = submitter.name;
        value.value = submitter.value;
        form.appendChild(value);
    }
    form.submit();
};

export const setupAdminInterface = () => {
    if (document.body.dataset.page !== 'admin') {
        return;
    }

    if (!adminSearchShortcutBound) {
        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }
            const target = event.target as HTMLElement | null;
            if (target?.matches('input, textarea, select, [contenteditable="true"]')) {
                return;
            }
            const search = document.querySelector<HTMLInputElement>('[data-admin-search-input]');
            if (!search) {
                return;
            }
            event.preventDefault();
            search.focus();
            search.select();
        });
        adminSearchShortcutBound = true;
    }

    document.querySelectorAll<HTMLFormElement>('[data-admin-bulk]').forEach((form) => {
        if (form.dataset.adminBulkBound === '1' || !form.id) {
            return;
        }
        form.dataset.adminBulkBound = '1';
        const rows = Array.from(
            document.querySelectorAll<HTMLInputElement>(`input[data-admin-row-select][form="${form.id}"]`),
        );
        const selectAll = document.querySelector<HTMLInputElement>(`[data-admin-select-all="${form.id}"]`);
        const count = form.querySelector<HTMLElement>('[data-admin-selected-count]');
        const availableRows = rows.filter((row) => !row.disabled);

        const update = () => {
            const selected = availableRows.filter((row) => row.checked).length;
            form.hidden = selected === 0;
            if (count) {
                count.textContent = String(selected);
            }
            if (selectAll) {
                selectAll.checked = availableRows.length > 0 && selected === availableRows.length;
                selectAll.indeterminate = selected > 0 && selected < availableRows.length;
            }
            rows.forEach((row) => row.closest('[data-admin-select-row], .admin-row')?.classList.toggle('is-selected', row.checked));
        };

        rows.forEach((row) => row.addEventListener('change', update));
        selectAll?.addEventListener('change', () => {
            availableRows.forEach((row) => {
                row.checked = selectAll.checked;
            });
            update();
        });
        update();
    });

    if (!adminActionMenusBound) {
        const closeActionMenus = () => {
            document.querySelectorAll<HTMLDetailsElement>('.admin-action-menu[open]').forEach((menu) => {
                menu.removeAttribute('open');
            });
        };
        document.addEventListener('click', (event) => {
            const target = event.target as Element | null;
            const currentMenu = target?.closest('.admin-action-menu');
            document.querySelectorAll<HTMLDetailsElement>('.admin-action-menu[open]').forEach((menu) => {
                if (menu !== currentMenu) {
                    menu.removeAttribute('open');
                }
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeActionMenus();
            }
        });
        adminActionMenusBound = true;
    }
};

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
            '[data-admin-queue], [data-admin-hide], [data-admin-restore], [data-admin-nsfw], [data-admin-dismiss]',
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
        const nsfwButtons = scope.querySelectorAll<HTMLButtonElement>('[data-admin-nsfw]');
        queueButtons.forEach((button) => {
            button.hidden = status === 'pending';
        });
        hideButtons.forEach((button) => {
            button.hidden = status === 'hidden';
        });
        restoreButtons.forEach((button) => {
            button.hidden = status === 'approved';
            const label = status === 'pending' ? button.dataset.allowLabel : button.dataset.restoreLabel;
            if (label) {
                button.textContent = label;
            }
        });
        nsfwButtons.forEach((button) => {
            button.hidden = false;
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
            if (!isEditMode() || hasPendingAction()) {
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
            button.disabled = true;
            scheduleUndoableAction(async () => {
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
                } finally {
                    button.disabled = false;
                }
            }, () => {
                button.disabled = false;
            });
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
              : button.hasAttribute('data-admin-dismiss')
                ? 'dismiss'
              : button.hasAttribute('data-admin-nsfw')
                ? 'nsfw'
                : 'restore';
        button.addEventListener('click', async () => {
            if (!isEditMode() || hasPendingAction()) {
                return;
            }
            const url = button.dataset.adminUrl ?? '';
            if (!url) {
                return;
            }
            const needsReason = action === 'queue' || action === 'hide';
            const nsfwEnabled = button.dataset.adminNsfwValue !== '0';
            const reason = needsReason ? await requestModerationReason({
                title: resolveModerationReasonTitle(action),
                placeholder: t('moderation_reason_placeholder', 'Explain why this action is needed.'),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            }) : null;
            if (needsReason && !reason) {
                return;
            }
            if (!needsReason && !await requestModerationConfirmation({
                title: resolveModerationConfirmationTitle(action === 'nsfw' && !nsfwEnabled ? 'nsfw_remove' : action),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            })) {
                return;
            }
            button.disabled = true;
            scheduleUndoableAction(async () => {
                try {
                    const body = needsReason
                        ? new URLSearchParams({_token: csrfToken, reason: reason ?? ''}).toString()
                        : action === 'nsfw'
                          ? new URLSearchParams({_token: csrfToken, nsfw: nsfwEnabled ? '1' : '0'}).toString()
                          : undefined;
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            ...(body ? {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'} : {}),
                        },
                        body,
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
                    const data = (await response.json()) as {status?: string; nsfw?: boolean};
                    if (action === 'dismiss') {
                        window.location.reload();
                        return;
                    }
                    const nextStatus = typeof data.status === 'string' ? data.status.toLowerCase() : '';
                    updateModerationScope(button, nextStatus);
                    if (action === 'nsfw') {
                        const enabled = data.nsfw ?? nsfwEnabled;
                        const label = enabled
                            ? t('moderation_remove_nsfw', 'Remove NSFW label')
                            : t('moderation_nsfw', 'NSFW');
                        button.dataset.adminNsfwValue = enabled ? '0' : '1';
                        button.textContent = label;
                        button.setAttribute('aria-label', label);
                        button.setAttribute('title', label);
                        const container = button.closest<HTMLElement>('[data-moderation-scope]')?.querySelector<HTMLElement>('.admin-moderation__meta');
                        const chip = container?.querySelector<HTMLElement>('.chip--nsfw');
                        if (enabled && container && !chip) {
                            const nextChip = document.createElement('span');
                            nextChip.className = 'chip chip--nsfw';
                            nextChip.textContent = 'NSFW';
                            container.appendChild(nextChip);
                        } else if (!enabled) {
                            chip?.remove();
                        }
                    }
                    if (['hide', 'restore', 'nsfw'].includes(action)) {
                        button.closest<HTMLElement>('[data-moderation-scope]')?.querySelector('[data-report-context]')?.remove();
                    }
                    button.closest('details')?.removeAttribute('open');
                    const messageKey = action === 'queue'
                        ? 'admin_queue_done'
                        : action === 'hide'
                          ? 'admin_hide_done'
                          : action === 'nsfw'
                            ? nsfwEnabled ? 'admin_nsfw_done' : 'admin_nsfw_removed'
                            : 'admin_restore_done';
                    const fallback = action === 'queue'
                        ? 'Queued for moderation.'
                        : action === 'hide'
                          ? 'Hidden.'
                          : action === 'nsfw'
                            ? nsfwEnabled ? 'Marked NSFW.' : 'NSFW label removed.'
                            : 'Restored.';
                    toast.show(t(messageKey, fallback));
                } catch {
                    toast.show(t('admin_action_failed', 'Moderation action failed.'));
                } finally {
                    button.disabled = false;
                }
            }, () => {
                button.disabled = false;
            });
        });
    });

    flagButtons.forEach((button) => {
        if (button.dataset.adminFlagBound === '1') {
            return;
        }
        button.dataset.adminFlagBound = '1';
        button.addEventListener('click', async () => {
            if (!isEditMode() || hasPendingAction()) {
                return;
            }
            if (!await requestModerationConfirmation({
                title: resolveModerationConfirmationTitle('flag'),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            })) {
                return;
            }
            const payload = {
                content_type: button.dataset.reportType ?? 'post',
                content_id: button.dataset.reportId ?? null,
                content_url: button.dataset.reportUrl ?? window.location.href,
                reason: 'other',
                details: 'Flagged by admin',
            };
            button.disabled = true;
            scheduleUndoableAction(async () => {
                const ok = await submitReport(payload);
                button.disabled = false;
                toast.show(ok ? t('admin_flagged', 'Flagged.') : t('report_failed', 'Unable to send report.'));
            }, () => {
                button.disabled = false;
            });
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
            if (hasPendingAction()) {
                return;
            }
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
            const submitter = (event as SubmitEvent).submitter as HTMLButtonElement | HTMLInputElement | null;
            scheduleUndoableAction(() => submitForm(form, submitter));
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
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (hasPendingAction()) {
                return;
            }
            const message = form.dataset.confirmMessage ?? t('confirm_action', 'Are you sure?');
            const confirmed = await requestModerationConfirmation({
                title: message,
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            });
            if (confirmed) {
                const submitter = (event as SubmitEvent).submitter as HTMLButtonElement | HTMLInputElement | null;
                scheduleUndoableAction(() => submitForm(form, submitter));
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
        select.addEventListener('change', async () => {
            const message = select.dataset.confirmMessage ?? t('confirm_action', 'Are you sure?');
            if (hasPendingAction() || !await requestModerationConfirmation({
                title: message,
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            })) {
                select.value = select.dataset.confirmPrev ?? select.value;
                return;
            }
            const form = select.closest<HTMLFormElement>('form');
            scheduleUndoableAction(() => {
                if (form) submitForm(form);
            }, () => {
                select.value = select.dataset.confirmPrev ?? select.value;
            });
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
            if (hasPendingAction()) {
                return;
            }
            const url = button.dataset.authorDeleteUrl ?? '';
            if (!url) {
                return;
            }
            const confirmed = await requestModerationConfirmation({
                title: t('author_delete_confirm', 'Delete this post?'),
                submitLabel: t('moderation_reason_submit', 'Confirm'),
            });
            if (!confirmed) {
                return;
            }
            button.disabled = true;
            scheduleUndoableAction(async () => {
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
                } finally {
                    button.disabled = false;
                }
            }, () => {
                button.disabled = false;
            });
        });
    });
};
