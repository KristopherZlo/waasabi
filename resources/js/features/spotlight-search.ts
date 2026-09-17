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
    let resultLinks: HTMLAnchorElement[] = [];
    let selectedIndex = -1;

    const setSelectedIndex = (nextIndex: number) => {
        if (!resultLinks.length) {
            selectedIndex = -1;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        selectedIndex = (nextIndex + resultLinks.length) % resultLinks.length;
        resultLinks.forEach((link, index) => {
            const selected = index === selectedIndex;
            link.classList.toggle('is-selected', selected);
            link.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        const selected = resultLinks[selectedIndex];
        input.setAttribute('aria-activedescendant', selected.id);
        selected.scrollIntoView({ block: 'nearest' });
    };

    const appendHighlightedText = (element: HTMLElement, value: string, query: string) => {
        const start = value.toLocaleLowerCase().indexOf(query.toLocaleLowerCase());
        if (start < 0) {
            element.textContent = value;
            return;
        }
        element.append(document.createTextNode(value.slice(0, start)));
        const mark = document.createElement('mark');
        mark.textContent = value.slice(start, start + query.length);
        element.append(mark, document.createTextNode(value.slice(start + query.length)));
    };

    const showMessage = (message: string) => {
        results.innerHTML = '';
        resultLinks = [];
        setSelectedIndex(-1);
        const empty = document.createElement('div');
        empty.className = 'search-spotlight__empty';
        empty.textContent = message;
        results.appendChild(empty);
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const render = (items: SearchItem[]) => {
        results.innerHTML = '';
        resultLinks = [];
        selectedIndex = -1;
        if (!items.length) {
            showMessage(t('search_empty', 'No results.'));
            return;
        }
        const fragment = document.createDocumentFragment();
        const query = input.value.trim();
        const itemTypes: SearchItem['type'][] = ['post', 'question', 'user', 'tag'];
        for (const type of itemTypes) {
            const groupItems = items.filter((item) => item.type === type);
            if (!groupItems.length) continue;

            const group = document.createElement('div');
            const heading = document.createElement('div');
            const headingId = 'search-group-' + type;
            group.className = 'search-spotlight__group';
            group.setAttribute('role', 'group');
            group.setAttribute('aria-labelledby', headingId);
            heading.className = 'search-spotlight__group-title';
            heading.id = headingId;
            heading.textContent = typeLabels[type];
            group.appendChild(heading);

            for (const item of groupItems) {
                const link = document.createElement('a');
                const index = resultLinks.length;
                link.className = 'search-spotlight__item';
                link.id = 'search-result-' + index;
                link.href = item.url;
                link.tabIndex = -1;
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');
                const title = document.createElement('div');
                title.className = 'search-spotlight__item-title';
                appendHighlightedText(title, item.title, query);
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
                link.addEventListener('mousemove', () => setSelectedIndex(index));
                link.addEventListener('focus', () => setSelectedIndex(index));
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    close();
                    void navigateTo(item.url);
                });
                resultLinks.push(link);
                group.appendChild(link);
            }
            fragment.appendChild(group);
        }
        results.appendChild(fragment);
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setSelectedIndex(0);
    };

    const search = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            currentItems = [];
            resultLinks = [];
            setSelectedIndex(-1);
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
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
        input.setAttribute('aria-expanded', 'false');
        setSelectedIndex(-1);
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
        input.setAttribute('aria-expanded', 'false');
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
        if (event.key === 'ArrowDown' && resultLinks.length) {
            event.preventDefault();
            setSelectedIndex(selectedIndex + 1);
        }
        if (event.key === 'ArrowUp' && resultLinks.length) {
            event.preventDefault();
            setSelectedIndex(selectedIndex - 1);
        }
        if (event.key === 'Home' && resultLinks.length) {
            event.preventDefault();
            setSelectedIndex(0);
        }
        if (event.key === 'End' && resultLinks.length) {
            event.preventDefault();
            setSelectedIndex(resultLinks.length - 1);
        }
        const selected = resultLinks[selectedIndex];
        if (event.key === 'Enter' && selected) {
            event.preventDefault();
            close();
            void navigateTo(selected.getAttribute('href') ?? selected.href);
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
