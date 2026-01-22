import { appUrl } from '../core/config';
import { t } from '../core/i18n';
import { getSearchIndex } from '../core/search';
import { getReadLaterList, setReadLaterList } from '../core/storage';
import type { SearchItem } from '../core/types';
import { updateCardsProgress } from '../features/reading';
import { setupIcons, setupImageFallbacks, setupScribbleAvatars } from '../core/media';
import { setupPostJumpButtons } from './post-jump';
import { setupNsfwReveal } from './nsfw';
import { setupCarousels } from './carousels';
import { setupIconTooltips } from './tooltips';

let readLaterMenuBound = false;
let readLaterPageRefreshPending = false;

const getReadLaterPageList = () =>
    document.querySelector<HTMLElement>('[data-read-later-page-list]');

const updateReadLaterPageEmptyState = () => {
    if (document.body.dataset.page !== 'read-later') {
        return;
    }
    const list = getReadLaterPageList();
    if (!list) {
        return;
    }
    const cards = Array.from(list.querySelectorAll<HTMLElement>('[data-feed-card]'));
    const emptyState = list.querySelector<HTMLElement>('[data-read-later-page-empty]');
    if (emptyState) {
        emptyState.hidden = cards.length > 0;
    }
};

const hydrateReadLaterPage = async (list: HTMLElement) => {
    setupIcons(list);
    setupImageFallbacks(list);
    setupScribbleAvatars(list);
    setupPostJumpButtons(list);
    setupNsfwReveal(list);
    setupCarousels(list);
    setupIconTooltips(list);

    const [{ bindActionMenus }, { bindActionToggles }, { setupReportModal }, { setupAuthorActions }] =
        await Promise.all([
            import('./action-menus'),
            import('./action-toggles'),
            import('./report'),
            import('./admin'),
        ]);

    bindActionMenus(list);
    bindActionToggles(list);
    setupReportModal(list);
    setupAuthorActions();

    updateCardsProgress();
    document.dispatchEvent(new CustomEvent('feed:updated'));
};

const refreshReadLaterPageFromServer = async () => {
    if (document.body.dataset.page !== 'read-later') {
        return;
    }
    if (document.body.dataset.authState !== '1') {
        return;
    }
    if (readLaterPageRefreshPending) {
        return;
    }

    const list = getReadLaterPageList();
    if (!list) {
        return;
    }

    readLaterPageRefreshPending = true;
    try {
        const emptyState = list.querySelector<HTMLElement>('[data-read-later-page-empty]');
        const emptyText = (emptyState?.textContent ?? '').trim() || 'Nothing saved yet.';

        const response = await fetch(`${appUrl}/read-later/render`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as { items?: string[]; slugs?: string[] };
        const items = Array.isArray(data.items) ? data.items : [];
        const slugs = Array.isArray(data.slugs) ? data.slugs : [];
        setReadLaterList(slugs);

        const emptyHtml = `<div class="list-item" data-read-later-page-empty hidden>${emptyText}</div>`;
        list.innerHTML = items.join('') + emptyHtml;
        await hydrateReadLaterPage(list);
        updateReadLaterPageEmptyState();
    } catch {
        // ignore
    } finally {
        readLaterPageRefreshPending = false;
    }
};

export const renderReadLaterMenu = () => {
    const menu = document.querySelector<HTMLElement>('[data-read-later-menu]');
    if (!menu) {
        return;
    }
    const list = menu.querySelector<HTMLElement>('[data-read-later-list]');
    const empty = menu.querySelector<HTMLElement>('[data-read-later-empty]');
    if (!list || !empty) {
        return;
    }

    const saved = getReadLaterList();
    const recent = saved.slice(-5).reverse();
    list.innerHTML = '';

    if (!recent.length) {
        empty.hidden = false;
        list.hidden = true;
        return;
    }

    empty.hidden = true;
    list.hidden = false;

    const index = getSearchIndex();
    const indexMap = new Map<string, SearchItem>();
    index.forEach((item) => {
        if (!item.slug) {
            return;
        }
        if (item.type !== 'post' && item.type !== 'question') {
            return;
        }
        indexMap.set(item.slug, item);
    });

    recent.forEach((slug) => {
        const entry = indexMap.get(slug);
        const href = entry?.url ?? `${appUrl}/projects/${slug}`;
        const title = entry?.title ?? slug;
        const typeLabel =
            entry?.type === 'question'
                ? t('search_type_question', 'Question')
                : t('search_type_post', 'Post');

        const link = document.createElement('a');
        link.className = 'read-later-menu__item';
        link.href = href;
        link.setAttribute('role', 'menuitem');

        const titleEl = document.createElement('div');
        titleEl.className = 'read-later-menu__title';
        titleEl.textContent = title;

        const metaEl = document.createElement('div');
        metaEl.className = 'read-later-menu__meta';
        metaEl.textContent = typeLabel;

        link.appendChild(titleEl);
        link.appendChild(metaEl);
        list.appendChild(link);
    });
};

export const renderReadLaterList = (options: { refreshPage?: boolean } = {}) => {
    renderReadLaterMenu();
    if (document.body.dataset.page !== 'read-later') {
        return;
    }

    if (options.refreshPage) {
        void refreshReadLaterPageFromServer();
        return;
    }

    updateReadLaterPageEmptyState();
    updateCardsProgress();
};

export const setupReadLaterMenu = () => {
    const toggle = document.querySelector<HTMLButtonElement>('[data-read-later-toggle]');
    const menu = document.querySelector<HTMLElement>('[data-read-later-menu]');
    if (!toggle || !menu) {
        return;
    }
    if (readLaterMenuBound) {
        return;
    }
    if (menu.dataset.readLaterBound === '1') {
        return;
    }
    menu.dataset.readLaterBound = '1';
    readLaterMenuBound = true;

    const setOpen = (open: boolean) => {
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const toggleMenu = () => {
        if (menu.hidden) {
            renderReadLaterMenu();
        }
        setOpen(menu.hidden);
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleMenu();
    });

    menu.addEventListener('click', (event) => {
        const target = event.target as HTMLElement | null;
        if (target?.closest('a')) {
            setOpen(false);
        }
    });

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (menu.hidden) {
                return;
            }
            const target = event.target as Node | null;
            if (!target) {
                setOpen(false);
                return;
            }
            if (menu.contains(target) || toggle.contains(target)) {
                return;
            }
            setOpen(false);
        },
        true,
    );

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
        }
    });
};
