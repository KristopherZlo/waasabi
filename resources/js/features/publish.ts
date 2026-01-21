import { clearPublishDraft, getPublishDraft, updatePublishDraft } from '../core/storage';

export const setupPublishForm = () => {
    const form = document.querySelector<HTMLFormElement>('[data-publish-form]');
    if (!form) {
        return;
    }
    if (form.dataset.publishFormBound === '1') {
        return;
    }
    form.dataset.publishFormBound = '1';
    const isEditing = form.dataset.editing === '1';

    const requiredFields = Array.from(form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('[data-required]'));
    const draftFields = Array.from(
        form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[data-draft-field]'),
    );
    const submitButton = form.querySelector<HTMLButtonElement>('[data-publish-submit]');
    const publishLoader = form.querySelector<HTMLElement>('[data-publish-loader]');
    const typeTabs = Array.from(form.querySelectorAll<HTMLButtonElement>('[data-publish-type-tab]'));
    const typeInput = form.querySelector<HTMLInputElement>('[data-publish-type-input]');
    const publishLabels = Array.from(form.querySelectorAll<HTMLElement>('[data-publish-label]'));
    const publishHelpers = Array.from(form.querySelectorAll<HTMLElement>('[data-publish-helper]'));
    const publishPlaceholders = Array.from(
        form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('[data-publish-placeholder]'),
    );
    let draftTimer: number | undefined;

    const getCurrentType = () => form.dataset.publishType ?? 'post';

    const isRequiredForType = (field: HTMLInputElement | HTMLTextAreaElement) => {
        const types = field.dataset.requiredType;
        if (!types) {
            return true;
        }
        const type = getCurrentType();
        return types
            .split(',')
            .map((entry) => entry.trim())
            .filter(Boolean)
            .includes(type);
    };

    const evaluate = () => {
        const ready = requiredFields.filter(isRequiredForType).every((field) => field.value.trim().length > 0);
        if (submitButton) {
            submitButton.disabled = !ready;
        }
        form.classList.toggle('is-ready', ready);
    };

    const applyPublishType = (nextType: string) => {
        const type = nextType === 'question' ? 'question' : 'post';
        form.dataset.publishType = type;

        typeTabs.forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.publishTypeTab === type);
        });

        publishLabels.forEach((label) => {
            const postText = label.dataset.postText ?? label.textContent ?? '';
            const questionText = label.dataset.questionText ?? postText;
            label.textContent = type === 'question' ? questionText : postText;
        });

        publishHelpers.forEach((helper) => {
            const postText = helper.dataset.postText ?? helper.textContent ?? '';
            const questionText = helper.dataset.questionText ?? postText;
            helper.textContent = type === 'question' ? questionText : postText;
        });

        publishPlaceholders.forEach((field) => {
            const postText = field.dataset.postPlaceholder ?? field.getAttribute('placeholder') ?? '';
            const questionText = field.dataset.questionPlaceholder ?? postText;
            field.setAttribute('placeholder', type === 'question' ? questionText : postText);
        });

        if (typeInput) {
            typeInput.value = type;
            typeInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        evaluate();
    };

    const restoreDraft = () => {
        const draft = isEditing ? null : getPublishDraft();
        if (!draft?.fields) {
            return;
        }
        draftFields.forEach((field) => {
            const key = field.dataset.draftField ?? '';
            if (!key) {
                return;
            }
            const value = draft.fields[key];
            if (typeof value === 'string' && value.length) {
                field.value = value;
            }
        });
    };

    const scheduleDraftSave = () => {
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(() => {
            const fields: Record<string, string> = {};
            draftFields.forEach((field) => {
                const key = field.dataset.draftField ?? '';
                if (key) {
                    fields[key] = field.value;
                }
            });
            updatePublishDraft({ fields });
        }, 600);
    };

    requiredFields.forEach((field) => {
        field.addEventListener('input', evaluate);
    });

    draftFields.forEach((field) => {
        field.addEventListener('input', scheduleDraftSave);
        field.addEventListener('change', scheduleDraftSave);
    });

    form.addEventListener('submit', () => {
        clearPublishDraft();
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-disabled', 'true');
        }
        if (publishLoader) {
            publishLoader.hidden = false;
        }
        form.setAttribute('aria-busy', 'true');
        document.body.classList.add('is-locked');
        form.classList.add('is-submitting');
    });

    if (!isEditing) {
        typeTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const type = tab.dataset.publishTypeTab ?? 'post';
                applyPublishType(type);
            });
        });
    }

    restoreDraft();
    applyPublishType(typeInput?.value ?? getCurrentType());
};
