import { t } from '../core/i18n';

type ModerationReasonConfig = {
    title?: string;
    placeholder?: string;
    submitLabel?: string;
};

let modalBound = false;
let pendingResolve: ((value: string | null) => void) | null = null;

const getElements = () => {
    const modal = document.querySelector<HTMLElement>('[data-moderation-modal]');
    if (!modal) {
        return null;
    }
    const form = modal.querySelector<HTMLFormElement>('[data-moderation-form]');
    const textarea = modal.querySelector<HTMLTextAreaElement>('[data-moderation-reason]');
    const title = modal.querySelector<HTMLElement>('[data-moderation-title]');
    const submit = modal.querySelector<HTMLButtonElement>('[data-moderation-submit]');
    const cancel = modal.querySelector<HTMLButtonElement>('[data-moderation-cancel]');
    const close = modal.querySelector<HTMLButtonElement>('[data-moderation-close]');
    const error = modal.querySelector<HTMLElement>('[data-moderation-error]');
    if (!form || !textarea) {
        return null;
    }
    return { modal, form, textarea, title, submit, cancel, close, error };
};

const resolveAndClose = (value: string | null) => {
    const elements = getElements();
    if (!elements) {
        pendingResolve?.(value);
        pendingResolve = null;
        return;
    }
    elements.modal.hidden = true;
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
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const reason = textarea.value.trim();
        if (!reason) {
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
    const { modal, textarea, title, submit, error } = elements;
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
    }
    if (submit) {
        const fallback = submit.dataset.defaultLabel ?? submit.textContent ?? '';
        submit.textContent = config.submitLabel ?? fallback;
    }
    modal.hidden = false;
    requestAnimationFrame(() => {
        textarea.focus();
    });

    return new Promise<string | null>((resolve) => {
        pendingResolve = resolve;
    });
};

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
