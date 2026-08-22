import { appUrl } from '../core/config';
import { t } from '../core/i18n';
import { rememberFocus, restoreFocus, trapModalFocus } from '../core/modal';
import type { SearchItem } from '../core/types';
import { navigateTo } from './spa';

let spotlightSearchBound = false;

export const setupSpotlightSearch = () => {
    const modal = document.querySelector<HTMLElement>('[data-search-modal]');
    const openButtons = Array.from(document.querySelectorAll<HTMLElement>('[data-search-open]'));
    if (!modal || !openButtons.length || spotlightSearchBound || modal.dataset.searchModalBound === '1') {
        return;
    }
    const input = modal.querySelector<HTMLInputElement>('[data-search-input]');
    const results = modal.querySelector<HTMLElement>('[data-search-results]');
    if (!input || !results) {
        return;
    }
    modal.dataset.searchModalBound = '1';
    spotlightSearchBound = true;

    const typeLabels: Record<SearchItem['type'], string> = {
        post: t('search_type_post', 'Post'),
        question: t('search_type_question', 'Question'),
        user: t('search_type_user', 'User'),
        tag: t('search_type_tag', 'Tag'),
    };
    let currentItems: SearchItem[] = [];
    let controller: AbortController | null = null;
    let timer: number | undefined;
    let returnFocus: HTMLElement | null = null;

    const showMessage = (message: string) => {
        results.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'search-spotlight__empty';
        empty.textContent = message;
        results.appendChild(empty);
        results.hidden = false;
    };

    const render = (items: SearchItem[]) => {
        results.innerHTML = '';
        if (!items.length) {
            showMessage(t('search_empty', 'No results.'));
            return;
        }
        const fragment = document.createDocumentFragment();
        for (const item of items) {
            const link = document.createElement('a');
            link.className = 'search-spotlight__item';
            link.href = item.url;
            const title = document.createElement('div');
            title.className = 'search-spotlight__item-title';
            title.textContent = item.title;
            link.appendChild(title);
            if (item.subtitle) {
                const subtitle = document.createElement('div');
                subtitle.className = 'search-spotlight__item-subtitle';
                subtitle.textContent = item.subtitle;
                link.appendChild(subtitle);
            }
            const meta = document.createElement('div');
            meta.className = 'search-spotlight__item-meta';
            meta.textContent = [typeLabels[item.type], item.author].filter(Boolean).join(' · ');
            link.appendChild(meta);
            fragment.appendChild(link);
        }
        results.appendChild(fragment);
        results.hidden = false;
    };

    const search = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            currentItems = [];
            results.hidden = true;
            return;
        }
        controller?.abort();
        controller = new AbortController();
        const url = new URL(`${appUrl}/search`, window.location.origin);
        url.searchParams.set('q', query);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                showMessage(t('search_empty', 'No results.'));
                return;
            }
            const payload = (await response.json()) as { items?: SearchItem[] };
            currentItems = payload.items ?? [];
            render(currentItems);
        } catch (error) {
            if ((error as DOMException).name !== 'AbortError') {
                showMessage(t('search_empty', 'No results.'));
            }
        }
    };

    const close = () => {
        controller?.abort();
        modal.hidden = true;
        document.body.classList.remove('is-locked');
        results.hidden = true;
        restoreFocus(returnFocus);
        returnFocus = null;
    };
    const open = () => {
        returnFocus = rememberFocus();
        modal.hidden = false;
        document.body.classList.add('is-locked');
        input.value = '';
        input.placeholder = t('search_placeholder', 'Search posts, questions, people, tags');
        results.hidden = true;
        window.setTimeout(() => input.focus(), 0);
    };

    openButtons.forEach((button) => button.addEventListener('click', open));
    modal.querySelectorAll<HTMLElement>('[data-search-close]').forEach((button) => button.addEventListener('click', close));
    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(search, 180);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
        if (event.key === 'Enter' && currentItems[0]) {
            close();
            void navigateTo(currentItems[0].url);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (!modal.hidden) {
            trapModalFocus(modal, event);
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            modal.hidden ? open() : close();
        } else if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
};
