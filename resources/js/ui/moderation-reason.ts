import { t } from '../core/i18n';
import { rememberFocus, restoreFocus, trapModalFocus } from '../core/modal';

type ModerationReasonConfig = {
    title?: string;
    placeholder?: string;
    submitLabel?: string;
    reasonRequired?: boolean;
};

let modalBound = false;
let pendingResolve: ((value: string | null) => void) | null = null;
let returnFocus: HTMLElement | null = null;

const getElements = () => {
    const modal = document.querySelector<HTMLElement>('[data-moderation-modal]');
    if (!modal) {
        return null;
    }
    const form = modal.querySelector<HTMLFormElement>('[data-moderation-form]');
    const textarea = modal.querySelector<HTMLTextAreaElement>('[data-moderation-reason]');
    const reasonField = textarea?.closest<HTMLElement>('label');
    const title = modal.querySelector<HTMLElement>('[data-moderation-title]');
    const submit = modal.querySelector<HTMLButtonElement>('[data-moderation-submit]');
    const cancel = modal.querySelector<HTMLButtonElement>('[data-moderation-cancel]');
    const close = modal.querySelector<HTMLButtonElement>('[data-moderation-close]');
    const error = modal.querySelector<HTMLElement>('[data-moderation-error]');
    if (!form || !textarea) {
        return null;
    }
    return { modal, form, textarea, reasonField, title, submit, cancel, close, error };
};

const resolveAndClose = (value: string | null) => {
    const elements = getElements();
    if (!elements) {
        pendingResolve?.(value);
        pendingResolve = null;
        return;
    }
    elements.modal.hidden = true;
    document.body.classList.remove('is-locked');
    restoreFocus(returnFocus);
    returnFocus = null;
    if (elements.error) {
        elements.error.hidden = true;
    }
    if (pendingResolve) {
        pendingResolve(value);
        pendingResolve = null;
    }
};

const ensureBound = () => {
    if (modalBound) {
        return;
    }
    const elements = getElements();
    if (!elements) {
        return;
    }
    const { modal, form, textarea, cancel, close, error, title, submit } = elements;
    modalBound = true;

    if (title && !title.dataset.defaultTitle) {
        title.dataset.defaultTitle = title.textContent ?? '';
    }
    if (!textarea.dataset.defaultPlaceholder) {
        textarea.dataset.defaultPlaceholder = textarea.placeholder ?? '';
    }
    if (submit && !submit.dataset.defaultLabel) {
        submit.dataset.defaultLabel = submit.textContent ?? '';
    }

    const closeModal = () => resolveAndClose(null);

    cancel?.addEventListener('click', closeModal);
    close?.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    window.addEventListener('keydown', (event) => {
        if (!modal.hidden) {
            trapModalFocus(modal, event);
        }
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const reason = textarea.value.trim();
        if (textarea.required && !reason) {
            if (error) {
                error.hidden = false;
            }
            textarea.focus();
            return;
        }
        resolveAndClose(reason);
    });

    textarea.addEventListener('input', () => {
        if (error) {
            error.hidden = true;
        }
    });
};

export const requestModerationReason = (config: ModerationReasonConfig = {}) => {
    const elements = getElements();
    if (!elements) {
        return Promise.resolve<string | null>(null);
    }
    ensureBound();
    const { modal, textarea, reasonField, title, submit, error } = elements;
    const reasonRequired = config.reasonRequired !== false;
    if (error) {
        error.hidden = true;
    }
    if (title) {
        const fallback = title.dataset.defaultTitle ?? title.textContent ?? '';
        title.textContent = config.title ?? fallback;
    }
    if (textarea) {
        const fallback = textarea.dataset.defaultPlaceholder ?? textarea.placeholder ?? '';
        textarea.value = '';
        textarea.placeholder = config.placeholder ?? fallback;
        textarea.required = reasonRequired;
    }
    if (reasonField) {
        reasonField.hidden = !reasonRequired;
    }
    if (submit) {
        const fallback = submit.dataset.defaultLabel ?? submit.textContent ?? '';
        submit.textContent = config.submitLabel ?? fallback;
    }
    returnFocus = rememberFocus();
    modal.hidden = false;
    document.body.classList.add('is-locked');
    requestAnimationFrame(() => {
        (reasonRequired ? textarea : submit)?.focus();
    });

    return new Promise<string | null>((resolve) => {
        pendingResolve = resolve;
    });
};

export const requestModerationConfirmation = async (config: Omit<ModerationReasonConfig, 'reasonRequired'> = {}) =>
    (await requestModerationReason({...config, reasonRequired: false})) !== null;

export const resolveModerationReasonTitle = (action: string) => {
    switch (action) {
        case 'ban':
            return t('moderation_reason_ban', 'Provide a ban reason');
        case 'unban':
            return t('moderation_reason_unban', 'Provide an unban reason');
        case 'queue':
            return t('moderation_reason_queue', 'Provide a moderation reason');
        case 'hide':
            return t('moderation_reason_hide', 'Provide a hide reason');
        case 'delete':
            return t('moderation_reason_delete', 'Provide a deletion reason');
        default:
            return t('moderation_reason_title', 'Provide a reason');
    }
};

export const resolveModerationConfirmationTitle = (action: string) => {
    switch (action) {
        case 'restore':
            return t('moderation_confirm_restore', 'Restore this content?');
        case 'dismiss':
            return t('moderation_confirm_dismiss', 'Allow this content and dismiss its reports?');
        case 'nsfw':
            return t('moderation_confirm_nsfw', 'Mark this content as sensitive?');
        case 'nsfw_remove':
            return t('moderation_confirm_nsfw_remove', 'Remove the sensitive-content label?');
        case 'flag':
            return t('moderation_confirm_flag', 'Send this content to moderation?');
        default:
            return t('confirm_action', 'Are you sure?');
    }
};
