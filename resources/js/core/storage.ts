import type { PageSettings, PublishDraft, ReadingState } from './types';

const parseJson = <T>(value: string | null, fallback: T) => {
    if (!value) {
        return fallback;
    }
    try {
        return JSON.parse(value) as T;
    } catch {
        return fallback;
    }
};

const publishDraftKey = (key?: string) => {
    const form = document.querySelector<HTMLFormElement>('[data-publish-form]');
    const draftId = key ?? form?.dataset.draftKey ?? form?.querySelector<HTMLInputElement>('[name=post_id]')?.value ?? '';
    return `waasabi:draft:${document.body.dataset.userId ?? 'guest'}:${draftId || 'new'}`;
};
const settingsKey = 'pageSettings';
const readLaterKey = 'readLater';
const upvoteKey = 'upvotes';

const defaultSettings: PageSettings = {
    theme: 'system',
    feedView: 'classic',
    publications: ['en'],
};

const readingKey = (slug: string) => `reading:${slug}`;

export const getPublishDraft = () => {
    try {
        return parseJson<PublishDraft | null>(localStorage.getItem(publishDraftKey()), null);
    } catch {
        return null;
    }
};

export const recoverPublishDraft = (form: HTMLFormElement | null) => {
    const draft = getPublishDraft();
    return form?.dataset.restoreDraft === '1' && draft && draft.updatedAt >= Number(form.dataset.savedAt ?? 0) ? draft : null;
};

const setPublishDraft = (draft: PublishDraft) => {
    try {
        localStorage.setItem(publishDraftKey(), JSON.stringify(draft));
    } catch {
        // Storage can be disabled or full; the server form remains usable.
    }
};

export const updatePublishDraft = (partial: Partial<PublishDraft>) => {
    const current = getPublishDraft() ?? { fields: {}, contentHtml: '', updatedAt: 0 };
    const next = {
        ...current,
        ...partial,
        fields: {
            ...current.fields,
            ...(partial.fields ?? {}),
        },
        updatedAt: Date.now(),
    };
    setPublishDraft(next);
};

export const clearPublishDraft = (key?: string) => {
    try {
        localStorage.removeItem(publishDraftKey(key));
    } catch {
        // No stored draft to clear when storage is unavailable.
    }
};

export const getSettings = () => parseJson<PageSettings>(localStorage.getItem(settingsKey), defaultSettings);

export const setSettings = (settings: PageSettings) => {
    localStorage.setItem(settingsKey, JSON.stringify(settings));
};

export const getReadingState = (slug: string) =>
    parseJson<ReadingState | null>(localStorage.getItem(readingKey(slug)), null);

export const setReadingState = (slug: string, state: ReadingState) => {
    localStorage.setItem(readingKey(slug), JSON.stringify(state));
};

export const getReadLaterList = () => parseJson<string[]>(localStorage.getItem(readLaterKey), []);

export const setReadLaterList = (list: string[]) => {
    localStorage.setItem(readLaterKey, JSON.stringify(list));
};

export const getUpvoteList = () => parseJson<string[]>(localStorage.getItem(upvoteKey), []);

export const setUpvoteList = (list: string[]) => {
    localStorage.setItem(upvoteKey, JSON.stringify(list));
};
