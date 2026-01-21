type SupportArticle = {
    id?: string;
    title?: string;
    summary?: string;
    url?: string;
    search?: string;
};

const normalize = (value: string) => value.trim().toLowerCase();

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
                const search = normalize(String(article.search ?? `${title} ${summary}`));
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
    const emptyState = root.querySelector<HTMLElement>('[data-support-empty]');

    if (!searchInput || items.length === 0) {
        return;
    }

    const applyFilter = () => {
        const query = normalize(searchInput.value);
        let visibleCount = 0;

        items.forEach((item) => {
            const search = normalize(item.dataset.supportSearch ?? '');
            const isVisible = query === '' || search.includes(query);
            item.hidden = !isVisible;
            if (isVisible) {
                visibleCount += 1;
            }
        });

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

    const tokensFromQuery = (query: string) =>
        query
            .split(/\s+/)
            .map((token) => token.trim())
            .filter((token) => token.length > 1);

    const scoreArticle = (article: SupportArticle, tokens: string[]) => {
        if (!article.search || !tokens.length) {
            return 0;
        }
        return tokens.reduce((score, token) => (article.search?.includes(token) ? score + 1 : score), 0);
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
        const query = normalize(`${subjectInput?.value ?? ''} ${bodyInput?.value ?? ''}`);
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
