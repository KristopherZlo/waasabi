import type { SearchItem } from './types';

export const getSearchIndex = () =>
    (window as unknown as { APP_SEARCH_INDEX?: SearchItem[] }).APP_SEARCH_INDEX ?? [];

export const normalizeQuery = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
