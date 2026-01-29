const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const getTags = (card: HTMLElement) =>
    (card.dataset.tags ?? '')
        .split(',')
        .map((tag) => slugify(tag))
        .filter(Boolean);

const getCardText = (card: HTMLElement) => {
    const title = card.querySelector('.post-title')?.textContent ?? '';
    const context = card.querySelector('.post-context')?.textContent ?? '';
    const tags = card.dataset.tags ?? '';
    return `${title} ${context} ${tags}`.toLowerCase();
};

export const setupCollaborationPage = () => {
    const root = document.querySelector<HTMLElement>('[data-collaboration-page]');
    if (!root) {
        return;
    }

    const list = root.querySelector<HTMLElement>('[data-collaboration-list]');
    if (!list) {
        return;
    }

    const roleSelect = root.querySelector<HTMLSelectElement>('[data-collaboration-filter="role"]');
    const availabilitySelect = root.querySelector<HTMLSelectElement>('[data-collaboration-filter="availability"]');
    const formatSelect = root.querySelector<HTMLSelectElement>('[data-collaboration-filter="format"]');
    const searchInput = root.querySelector<HTMLInputElement>('[data-collaboration-search]');
    const emptyState = root.querySelector<HTMLElement>('[data-collaboration-empty]');

    const getCards = () => Array.from(list.querySelectorAll<HTMLElement>('[data-feed-card]'));

    const applyFilters = () => {
        const role = slugify(roleSelect?.value ?? '');
        const availability = slugify(availabilitySelect?.value ?? '');
        const format = slugify(formatSelect?.value ?? '');
        const query = (searchInput?.value ?? '').trim().toLowerCase();

        let visibleCount = 0;
        getCards().forEach((card) => {
            const tags = getTags(card);
            const text = getCardText(card);

            const roleMatch = role ? tags.includes(role) : true;
            const availabilityMatch = availability ? tags.includes(availability) : true;
            const formatMatch = format ? tags.includes(format) : true;
            const searchMatch = query ? text.includes(query) : true;

            const show = roleMatch && availabilityMatch && formatMatch && searchMatch;
            card.hidden = !show;
            if (show) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    [roleSelect, availabilitySelect, formatSelect].forEach((select) => {
        if (!select) {
            return;
        }
        select.addEventListener('change', applyFilters);
    });
    searchInput?.addEventListener('input', applyFilters);

    applyFilters();
};
