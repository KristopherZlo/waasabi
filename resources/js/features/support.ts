type SupportArticle = {
    id?: string;
    title?: string;
    summary?: string;
    url?: string;
    search?: string;
};

const normalize = (value: string) => value.trim().toLowerCase();
const normalizeSearch = (value: string) => {
    const lower = normalize(value);
    try {
        return lower.replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    } catch {
        return lower.replace(/[^a-z0-9а-яёäöåü]+/gi, ' ').trim();
    }
};

const tokensFromQuery = (query: string) =>
    normalizeSearch(query)
        .split(/\s+/)
        .map((token) => token.trim())
        .filter((token) => token.length > 1);

const fuzzyScore = (token: string, text: string) => {
    if (!token || !text) {
        return 0;
    }

    if (text.includes(token)) {
        return 1 + token.length / Math.max(6, text.length);
    }

    let score = 0;
    let index = 0;
    let streak = 0;

    for (const char of token) {
        const nextIndex = text.indexOf(char, index);
        if (nextIndex === -1) {
            return 0;
        }
        if (nextIndex === index) {
            streak += 1;
            score += 2 + streak;
        } else {
            streak = 0;
            score += 1;
        }
        index = nextIndex + 1;
    }

    return score / Math.max(8, text.length);
};

const scoreTokens = (tokens: string[], text: string, weight: number) =>
    tokens.reduce((total, token) => total + fuzzyScore(token, text) * weight, 0);

const parseSupportArticles = (root: HTMLElement): SupportArticle[] => {
    const raw = root.dataset.supportArticles ?? '';
    if (!raw) {
        return [];
    }
    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed
            .map((article) => {
                if (!article || typeof article !== 'object') {
                    return null;
                }
                const title = String(article.title ?? '').trim();
                const summary = String(article.summary ?? '').trim();
                const search = normalizeSearch(String(article.search ?? `${title} ${summary}`));
                return {
                    id: String(article.id ?? ''),
                    title,
                    summary,
                    url: String(article.url ?? ''),
                    search,
                } as SupportArticle;
            })
            .filter(Boolean) as SupportArticle[];
    } catch {
        return [];
    }
};

const setupKnowledgeSearch = (root: HTMLElement) => {
    const searchInput = root.querySelector<HTMLInputElement>('[data-support-search]');
    const items = Array.from(root.querySelectorAll<HTMLElement>('[data-support-item]'));
    const groups = Array.from(root.querySelectorAll<HTMLElement>('[data-support-group]'));
    const emptyState = root.querySelector<HTMLElement>('[data-support-empty]');
    const listParents = new Set(items.map((item) => item.parentElement));
    const canReorder = listParents.size === 1;
    const list = canReorder ? items[0]?.parentElement ?? null : null;
    const groupOpenStates = new Map(groups.map((group) => [group, group instanceof HTMLDetailsElement ? group.open : false]));

    if (!searchInput || items.length === 0) {
        return;
    }

    const indexedItems = items.map((item) => {
        const titleText = item.querySelector('.support-knowledge__title')?.textContent ?? '';
        const summaryText = item.querySelector('.support-knowledge__text')?.textContent ?? '';
        const title = normalizeSearch(titleText);
        const summary = normalizeSearch(summaryText);
        const search = normalizeSearch(item.dataset.supportSearch ?? `${title} ${summary}`);
        return { item, title, summary, search };
    });
    const originalOrder = [...indexedItems];

    const applyFilter = () => {
        const tokens = tokensFromQuery(searchInput.value);
        let visibleCount = 0;

        const ranked = indexedItems
            .map(({ item, title, summary, search }) => {
                if (!tokens.length) {
                    return { item, score: 1 };
                }
                const score =
                    scoreTokens(tokens, title, 4) +
                    scoreTokens(tokens, summary, 2) +
                    scoreTokens(tokens, search, 1);
                return { item, score };
            })
            .sort((a, b) => b.score - a.score);

        ranked.forEach(({ item, score }) => {
            const isVisible = score > 0;
            item.hidden = !isVisible;
            if (isVisible) {
                visibleCount += 1;
            }
        });

        if (list && tokens.length) {
            const anchor = emptyState && emptyState.parentElement === list ? emptyState : null;
            ranked.forEach(({ item }) => {
                if (anchor) {
                    list.insertBefore(item, anchor);
                } else {
                    list.appendChild(item);
                }
            });
        } else if (list && !tokens.length) {
            const anchor = emptyState && emptyState.parentElement === list ? emptyState : null;
            originalOrder.forEach(({ item }) => {
                if (anchor) {
                    list.insertBefore(item, anchor);
                } else {
                    list.appendChild(item);
                }
            });
        }

        if (groups.length) {
            groups.forEach((group) => {
                const groupItems = Array.from(group.querySelectorAll<HTMLElement>('[data-support-item]'));
                const hasVisible = groupItems.some((item) => !item.hidden);
                group.hidden = !hasVisible;
                if (group instanceof HTMLDetailsElement) {
                    if (tokens.length && hasVisible) {
                        group.open = true;
                    } else if (!tokens.length) {
                        group.open = groupOpenStates.get(group) ?? false;
                    }
                }
            });
        }

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    searchInput.addEventListener('input', applyFilter);
    applyFilter();
};

const setupSuggestions = (root: HTMLElement) => {
    const subjectInput = root.querySelector<HTMLInputElement>('[data-support-ticket-subject]');
    const bodyInput = root.querySelector<HTMLTextAreaElement>('[data-support-ticket-body]');
    const list = root.querySelector<HTMLElement>('[data-support-suggestions-list]');
    const emptyState = root.querySelector<HTMLElement>('[data-support-suggestions-empty]');
    const container = root.querySelector<HTMLElement>('[data-support-suggestions]');

    if (!container || !list) {
        return;
    }

    const articles = parseSupportArticles(root);
    if (!articles.length) {
        return;
    }

    const scoreArticle = (article: SupportArticle, tokens: string[]) => {
        if (!tokens.length) {
            return 0;
        }
        const title = normalizeSearch(article.title ?? '');
        const summary = normalizeSearch(article.summary ?? '');
        const search = normalizeSearch(article.search ?? `${title} ${summary}`);
        return (
            scoreTokens(tokens, title, 4) +
            scoreTokens(tokens, summary, 2) +
            scoreTokens(tokens, search, 1)
        );
    };

    const renderSuggestions = (items: SupportArticle[]) => {
        list.innerHTML = '';
        items.forEach((article) => {
            const link = document.createElement('a');
            link.className = 'support-suggestion';
            link.href = article.url && article.url !== '' ? article.url : '#';

            const title = document.createElement('div');
            title.className = 'support-suggestion__title';
            title.textContent = article.title ?? '';

            const summary = document.createElement('div');
            summary.className = 'support-suggestion__text';
            summary.textContent = article.summary ?? '';

            link.append(title, summary);
            list.appendChild(link);
        });

        if (emptyState) {
            emptyState.hidden = items.length > 0;
        }
    };

    const updateSuggestions = () => {
        const query = `${subjectInput?.value ?? ''} ${bodyInput?.value ?? ''}`.trim();
        if (!query) {
            renderSuggestions(articles.slice(0, 3));
            return;
        }

        const tokens = tokensFromQuery(query);
        const ranked = articles
            .map((article) => ({ article, score: scoreArticle(article, tokens) }))
            .filter((entry) => entry.score > 0)
            .sort((a, b) => b.score - a.score)
            .map((entry) => entry.article)
            .slice(0, 3);

        renderSuggestions(ranked);
    };

    subjectInput?.addEventListener('input', updateSuggestions);
    bodyInput?.addEventListener('input', updateSuggestions);
    updateSuggestions();
};

export const setupSupportFaq = () => {
    const root = document.querySelector<HTMLElement>('[data-support-root]');
    if (!root || root.dataset.supportBound === '1') {
        return;
    }
    root.dataset.supportBound = '1';

    setupKnowledgeSearch(root);
    setupSuggestions(root);
};
