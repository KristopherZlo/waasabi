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

const publishDraftKey = 'draft:publish';
const settingsKey = 'pageSettings';
const readLaterKey = 'readLater';
const upvoteKey = 'upvotes';

const defaultSettings: PageSettings = {
    theme: 'system',
    feedView: 'classic',
    publications: ['en'],
};

const readingKey = (slug: string) => `reading:${slug}`;

export const getPublishDraft = () => parseJson<PublishDraft | null>(localStorage.getItem(publishDraftKey), null);

const setPublishDraft = (draft: PublishDraft) => {
    localStorage.setItem(publishDraftKey, JSON.stringify(draft));
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

export const clearPublishDraft = () => {
    localStorage.removeItem(publishDraftKey);
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
