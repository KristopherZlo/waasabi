import { t } from '../core/i18n';
import { normalizeQuery } from '../core/search';
import type { SearchItem } from '../core/types';
import { navigateTo } from './spa';

let spotlightSearchBound = false;

export const setupSpotlightSearch = () => {
    const modal = document.querySelector<HTMLElement>('[data-search-modal]');
    const openButtons = Array.from(document.querySelectorAll<HTMLElement>('[data-search-open]'));
    if (!modal || !openButtons.length) {
        return;
    }
    if (spotlightSearchBound) {
        return;
    }
    if (modal.dataset.searchModalBound === '1') {
        return;
    }
    modal.dataset.searchModalBound = '1';
    spotlightSearchBound = true;

    const input = modal.querySelector<HTMLInputElement>('[data-search-input]');
    const results = modal.querySelector<HTMLElement>('[data-search-results]');
    const closeButtons = Array.from(modal.querySelectorAll<HTMLElement>('[data-search-close]'));
    if (!input || !results) {
        return;
    }

    const rawIndex = (window as unknown as { APP_SEARCH_INDEX?: SearchItem[] }).APP_SEARCH_INDEX ?? [];
    const index = rawIndex.map((item) => {
        const text = normalizeQuery(
            [item.title, item.subtitle, item.slug, item.author, item.keywords, item.type]
                .filter(Boolean)
                .join(' '),
        );
        return { ...item, _searchText: text };
    }) as Array<SearchItem & { _searchText: string }>;

    const typeLabels: Record<SearchItem['type'], string> = {
        post: t('search_type_post', 'Post'),
        question: t('search_type_question', 'Question'),
        user: t('search_type_user', 'User'),
        tag: t('search_type_tag', 'Tag'),
    };

    const scoreToken = (token: string, text: string) => {
        if (!token) {
            return 0;
        }
        const directIndex = text.indexOf(token);
        let score = directIndex >= 0 ? 6 + Math.max(0, 4 - directIndex) : 0;
        let lastIndex = -1;
        let streak = 0;
        for (const char of token) {
            const idx = text.indexOf(char, lastIndex + 1);
            if (idx === -1) {
                return 0;
            }
            if (idx === lastIndex + 1) {
                streak += 1;
                score += 2 + streak;
            } else {
                streak = 0;
                score += 1;
            }
            lastIndex = idx;
        }
        return score;
    };

    const scoreItem = (query: string, text: string) => {
        if (!query) {
            return 0;
        }
        const tokens = query.split(/\s+/).filter(Boolean);
        let score = 0;
        for (const token of tokens) {
            const tokenScore = scoreToken(token, text);
            if (!tokenScore) {
                return 0;
            }
            score += tokenScore;
        }
        return score;
    };

    const setResultsVisible = (visible: boolean) => {
        results.hidden = !visible;
    };

    const renderEmpty = (message: string) => {
        results.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'search-spotlight__empty';
        empty.textContent = message;
        results.appendChild(empty);
    };

    const renderResults = (items: SearchItem[], query: string) => {
        results.innerHTML = '';
        if (!items.length) {
            renderEmpty(
                query
                    ? t('search_empty', 'No results.')
                    : t('search_hint', 'Type to search posts, questions, people, and tags.'),
            );
            return;
        }
        const fragment = document.createDocumentFragment();
        items.forEach((item) => {
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

            const type = document.createElement('span');
            type.className = 'search-spotlight__item-type';
            type.textContent = typeLabels[item.type];
            meta.appendChild(type);

            if (item.author) {
                const author = document.createElement('span');
                author.textContent = item.author;
                meta.appendChild(author);
            }

            link.appendChild(meta);
            fragment.appendChild(link);
        });

        results.appendChild(fragment);
    };

    let lastResults: SearchItem[] = [];
    const runSearch = (raw: string) => {
        const query = normalizeQuery(raw);
        if (!query) {
            lastResults = [];
            results.innerHTML = '';
            setResultsVisible(false);
            return;
        }
        setResultsVisible(true);
        const scored = index
            .map((item) => ({
                item,
                score: scoreItem(query, item._searchText),
            }))
            .filter((entry) => entry.score > 0)
            .sort((a, b) => b.score - a.score)
            .slice(0, 10)
            .map((entry) => entry.item);
        lastResults = scored;
        renderResults(scored, query);
    };

    let searchTimer: number | undefined;
    const scheduleSearch = () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            runSearch(input.value);
        }, 120);
    };

    const open = () => {
        modal.hidden = false;
        document.body.classList.add('is-locked');
        input.placeholder = t('search_placeholder', 'Search posts, questions, people, tags');
        input.value = '';
        results.innerHTML = '';
        setResultsVisible(false);
        window.setTimeout(() => input.focus(), 0);
    };

    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('is-locked');
        results.innerHTML = '';
        setResultsVisible(false);
    };

    openButtons.forEach((button) => {
        if (button.dataset.searchOpenBound === '1') {
            return;
        }
        button.dataset.searchOpenBound = '1';
        button.addEventListener('click', open);
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', close);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (event.key === 'Enter' && lastResults.length) {
            close();
            void navigateTo(lastResults[0].url);
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (modal.hidden) {
                open();
            } else {
                close();
            }
        }
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
};
