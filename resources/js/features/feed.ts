import { navigateTo } from './spa';
import type { DomRoot } from '../core/types';
import { setupIcons, setupImageFallbacks, setupScribbleAvatars } from '../core/media';
import { bindActionMenus } from '../ui/action-menus';
import { bindActionToggles } from '../ui/action-toggles';
import { setupReportModal } from '../ui/report';
import { setupAuthorActions } from '../ui/admin';
import { setupPostJumpButtons } from '../ui/post-jump';
import { setupNsfwReveal } from '../ui/nsfw';
import { setupCarousels } from '../ui/carousels';
import { setupIconTooltips } from '../ui/tooltips';
import { setupMediaViewer } from '../ui/media-viewer';
import { updateCardsProgress } from './reading';

let feedInfiniteCleanup: (() => void) | null = null;
let feedTabsUpdatedHandler: (() => void) | null = null;
let feedFiltersUpdatedHandler: (() => void) | null = null;

export const setupFeedFilters = () => {
    const filters = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-feed-filter]'));
    const tags = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-feed-tag]'));
    const emptyState = document.querySelector<HTMLElement>('[data-feed-empty]');
    const loader = document.querySelector<HTMLElement>('[data-feed-loader]');
    if (!filters.length) {
        if (feedFiltersUpdatedHandler) {
            document.removeEventListener('feed:updated', feedFiltersUpdatedHandler);
            feedFiltersUpdatedHandler = null;
        }
        return;
    }

    const getItems = () =>
        Array.from(document.querySelectorAll<HTMLElement>('[data-feed-card], [data-feed-placeholder]'));
    const getCardTags = (card: HTMLElement) =>
        (card.dataset.tags ?? '')
            .split(',')
            .map((tag) => tag.trim())
            .filter(Boolean);
    const isLoading = () => (loader ? !loader.hidden : false);

    const slugify = (value: string) =>
        value
            .toLowerCase()
            .trim()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

    const tagSynonymGroups: string[][] = [
        ['hardware', 'pcb', 'electronics', 'power', 'sensors'],
        ['writing', 'docs', 'notes', 'documentation', 'copy'],
        ['process', 'planning', 'workflow', 'team'],
        ['prototype', 'mvp', 'poc', 'labs'],
        ['ux', 'ui', 'product', 'design'],
        ['metrics', 'analytics', 'data'],
    ];

    const buildSynonymMap = () => {
        const map = new Map<string, Set<string>>();
        const addGroup = (values: string[]) => {
            const normalized = values.map(slugify).filter(Boolean);
            if (!normalized.length) {
                return;
            }
            const group = new Set(normalized);
            normalized.forEach((slug) => {
                const existing = map.get(slug);
                if (existing) {
                    normalized.forEach((item) => existing.add(item));
                } else {
                    map.set(slug, new Set(group));
                }
            });
        };

        tagSynonymGroups.forEach((group) => addGroup(group));

        tags.forEach((tag) => {
            const tagSlug = slugify(tag.dataset.feedTag ?? '');
            if (!tagSlug) {
                return;
            }
            const synonyms = (tag.dataset.feedSynonyms ?? '')
                .split(',')
                .map((value) => slugify(value))
                .filter(Boolean);
            if (synonyms.length) {
                addGroup([tagSlug, ...synonyms]);
            }
        });

        return map;
    };

    const synonymMap = buildSynonymMap();
    const hasTagMatch = (cardTags: string[], activeTag: string) => {
        const synonyms = synonymMap.get(activeTag);
        if (!synonyms) {
            return cardTags.includes(activeTag);
        }
        return cardTags.some((tag) => synonyms.has(tag));
    };

    const applyTagFilter = (activeTags: string[], excludedTags: string[]) => {
        const cards = getItems();
        let visibleCount = 0;

        cards.forEach((card) => {
            const cardTags = getCardTags(card);
            let show = true;

            if (activeTags.length) {
                show = show && activeTags.every((tag) => hasTagMatch(cardTags, tag));
            }
            if (excludedTags.length) {
                show = show && !excludedTags.some((tag) => hasTagMatch(cardTags, tag));
            }

            card.style.display = show ? '' : 'none';
            const visible = show && !card.hidden;
            if (visible) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0 || isLoading();
        }
    };

    const updateUrl = (filterName: string, activeTags: string[], excludedTags: string[]) => {
        const url = new URL(window.location.href);
        url.searchParams.set('filter', filterName);
        if (activeTags.length) {
            url.searchParams.set('tags', activeTags.join(','));
        } else {
            url.searchParams.delete('tags');
        }
        if (excludedTags.length) {
            url.searchParams.set('exclude', excludedTags.join(','));
        } else {
            url.searchParams.delete('exclude');
        }
        window.history.replaceState({}, '', url.toString());
    };

    const getActiveTags = () =>
        tags
            .filter((tag) => tag.classList.contains('is-active'))
            .map((tag) => slugify(tag.dataset.feedTag ?? ''))
            .filter(Boolean);

    const getExcludedTags = () =>
        tags
            .filter((tag) => tag.classList.contains('is-excluded'))
            .map((tag) => slugify(tag.dataset.feedTag ?? ''))
            .filter(Boolean);

    const setActiveFilter = (name: string) => {
        filters.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.feedFilter === name);
        });
    };

    const setActiveFromUrl = () => {
        const url = new URL(window.location.href);
        const filterName = url.searchParams.get('filter') ?? 'all';
        const tagParam = url.searchParams.get('tags') ?? '';
        const excludeParam = url.searchParams.get('exclude') ?? '';
        const urlTags = tagParam
            .split(',')
            .map((tag) => slugify(tag))
            .filter(Boolean);
        const urlExcluded = excludeParam
            .split(',')
            .map((tag) => slugify(tag))
            .filter(Boolean);
        const filteredTags = urlTags.filter((tag) => !urlExcluded.includes(tag));

        filters.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.feedFilter === filterName);
        });

        tags.forEach((btn) => {
            const tagName = slugify(btn.dataset.feedTag ?? '');
            btn.classList.toggle('is-active', filteredTags.includes(tagName));
            btn.classList.toggle('is-excluded', urlExcluded.includes(tagName));
            if (btn.classList.contains('is-excluded')) {
                btn.classList.remove('is-active');
            }
        });

        applyTagFilter(filteredTags, urlExcluded);
    };

    filters.forEach((filter) => {
        if (filter.dataset.feedFilterBound === '1') {
            return;
        }
        filter.dataset.feedFilterBound = '1';
        filter.addEventListener('click', () => {
            const name = filter.dataset.feedFilter ?? 'all';
            // Optimistic active state so the UI responds immediately.
            setActiveFilter(name);
            const activeTags = getActiveTags();
            const excludedTags = getExcludedTags();
            const url = new URL(window.location.href);
            url.searchParams.set('filter', name);
            if (activeTags.length) {
                url.searchParams.set('tags', activeTags.join(','));
            } else {
                url.searchParams.delete('tags');
            }
            if (excludedTags.length) {
                url.searchParams.set('exclude', excludedTags.join(','));
            } else {
                url.searchParams.delete('exclude');
            }
            void navigateTo(url.toString());
        });
    });

    tags.forEach((tag) => {
        if (tag.dataset.feedTagBound === '1') {
            return;
        }
        tag.dataset.feedTagBound = '1';
        tag.addEventListener('click', () => {
            tag.classList.toggle('is-active');
            if (tag.classList.contains('is-active')) {
                tag.classList.remove('is-excluded');
            }
            const activeTags = getActiveTags();
            const excludedTags = getExcludedTags();
            const activeFilter = filters.find((btn) => btn.classList.contains('is-active'))?.dataset.feedFilter ?? 'all';
            applyTagFilter(activeTags, excludedTags);
            updateUrl(activeFilter, activeTags, excludedTags);
        });
        tag.addEventListener('contextmenu', (event) => {
            event.preventDefault();
            tag.classList.toggle('is-excluded');
            if (tag.classList.contains('is-excluded')) {
                tag.classList.remove('is-active');
            }
            const activeTags = getActiveTags();
            const excludedTags = getExcludedTags();
            const activeFilter = filters.find((btn) => btn.classList.contains('is-active'))?.dataset.feedFilter ?? 'all';
            applyTagFilter(activeTags, excludedTags);
            updateUrl(activeFilter, activeTags, excludedTags);
        });
    });

    setActiveFromUrl();

    if (feedFiltersUpdatedHandler) {
        document.removeEventListener('feed:updated', feedFiltersUpdatedHandler);
    }
    feedFiltersUpdatedHandler = () => {
        const activeTags = getActiveTags();
        const excludedTags = getExcludedTags();
        applyTagFilter(activeTags, excludedTags);
    };
    document.addEventListener('feed:updated', feedFiltersUpdatedHandler);
};

export const setupFeedTabs = () => {
    const tabs = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-feed-tab]'));
    const emptyState = document.querySelector<HTMLElement>('[data-feed-empty]');
    const loader = document.querySelector<HTMLElement>('[data-feed-loader]');
    if (!tabs.length) {
        if (feedTabsUpdatedHandler) {
            document.removeEventListener('feed:updated', feedTabsUpdatedHandler);
            feedTabsUpdatedHandler = null;
        }
        return;
    }

    const typesByTab: Record<string, string[]> = {
        projects: ['projects', 'qa'],
        questions: ['questions'],
        collaboration: [],
    };

    const getItems = () => Array.from(document.querySelectorAll<HTMLElement>('[data-feed-type]'));
    const isLoading = () => (loader ? !loader.hidden : false);

    const isItemVisible = (item: HTMLElement) =>
        !item.hidden && item.style.display !== 'none' && !item.classList.contains('is-hidden');

    const applyTab = (name: string) => {
        const items = getItems();
        const tab = typesByTab[name] ? name : 'projects';
        const allowed = typesByTab[tab] ?? ['projects'];
        tabs.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.feedTab === tab);
        });

        let visibleCount = 0;
        items.forEach((item) => {
            const type = item.dataset.feedType ?? 'projects';
            const shouldShow = allowed.includes(type);
            item.hidden = !shouldShow;
            if (shouldShow && isItemVisible(item)) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0 || isLoading();
        }

        const url = new URL(window.location.href);
        url.searchParams.set('stream', tab);
        window.history.replaceState({}, '', url.toString());
    };

    const url = new URL(window.location.href);
    const initial = url.searchParams.get('stream') ?? 'projects';
    applyTab(initial);

    tabs.forEach((tab) => {
        if (tab.dataset.feedTabBound === '1') {
            return;
        }
        tab.dataset.feedTabBound = '1';
        tab.addEventListener('click', () => {
            const name = tab.dataset.feedTab ?? 'projects';
            applyTab(name);
        });
    });

    if (feedTabsUpdatedHandler) {
        document.removeEventListener('feed:updated', feedTabsUpdatedHandler);
    }
    feedTabsUpdatedHandler = () => {
        const active = tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.feedTab ?? initial;
        applyTab(active);
    };
    document.addEventListener('feed:updated', feedTabsUpdatedHandler);
};

export const insertFeedQaBlocks = () => {
    if (document.body.dataset.page !== 'feed') {
        return;
    }
    const list = document.querySelector<HTMLElement>('[data-feed-list]');
    const template = document.querySelector<HTMLTemplateElement>('[data-qa-template]');
    if (!list || !template) {
        return;
    }
    const intervalRaw = Number(list.dataset.qaInterval ?? 50);
    const interval = Number.isFinite(intervalRaw) && intervalRaw > 0 ? intervalRaw : 50;
    if (interval <= 0) {
        return;
    }

    let projectCount = 0;
    let inserted = false;
    const children = Array.from(list.children);
    children.forEach((child) => {
        if (!child.matches('[data-feed-card][data-feed-type="projects"]')) {
            return;
        }
        projectCount += 1;
        if (projectCount % interval !== 0) {
            return;
        }
        const anchorKey = String(projectCount);
        const existing = list.querySelector<HTMLElement>(`[data-qa-block][data-qa-anchor="${anchorKey}"]`);
        if (existing) {
            return;
        }
        const fragment = template.content.cloneNode(true) as DocumentFragment;
        const block = fragment.firstElementChild as HTMLElement | null;
        if (!block) {
            return;
        }
        block.dataset.qaAnchor = anchorKey;
        child.after(fragment);
        inserted = true;
    });
    if (inserted) {
        setupIcons(list);
        setupScribbleAvatars(list);
    }
    setupQaBlocks();
};

const hydrateFeedList = (root: DomRoot) => {
    insertFeedQaBlocks();
    setupIcons(root);
    setupImageFallbacks(root);
    setupScribbleAvatars(root);
    setupPostJumpButtons(root);
    setupNsfwReveal(root);
    setupCarousels(root);
    setupMediaViewer(root);
    setupIconTooltips(root);
    bindActionMenus(root);
    bindActionToggles(root);
    setupReportModal(root);
    setupAuthorActions();
    setupQaBlocks();
    updateCardsProgress();
    document.dispatchEvent(new CustomEvent('feed:updated'));
};

export const setupInfiniteFeed = () => {
    if (feedInfiniteCleanup) {
        feedInfiniteCleanup();
        feedInfiniteCleanup = null;
    }
    const list = document.querySelector<HTMLElement>('[data-feed-list]');
    if (!list) {
        return;
    }
    const endpoint = list.dataset.feedEndpoint ?? '';
    const sentinel = list.querySelector<HTMLElement>('[data-feed-sentinel]');
    const loader = list.querySelector<HTMLElement>('[data-feed-loader]');
    if (!endpoint || !sentinel) {
        return;
    }

    const pageSize = Math.max(1, Number(list.dataset.feedPageSize ?? 10) || 10);
    const maxLive = Math.max(pageSize * 2, 20);
    const streams = ['projects', 'questions'];

    const getDatasetNumber = (key: string, fallback: number) => {
        const value = (list.dataset as Record<string, string>)[key];
        const parsed = Number(value ?? fallback);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const stateByStream: Record<string, { offset: number; total: number; hasMore: boolean; isLoading: boolean }> = {};

    streams.forEach((stream) => {
        const suffix = `${stream.charAt(0).toUpperCase()}${stream.slice(1)}`;
        const offset = getDatasetNumber(`feedOffset${suffix}`, 0);
        const total = getDatasetNumber(`feedTotal${suffix}`, 0);
        stateByStream[stream] = {
            offset,
            total,
            hasMore: total === 0 ? true : offset < total,
            isLoading: false,
        };
    });

    const getActiveStream = () => {
        const active = document.querySelector<HTMLElement>('[data-feed-tab].is-active')?.dataset.feedTab ?? 'projects';
        return streams.includes(active) ? active : null;
    };

    const getActiveFilter = () => {
        const url = new URL(window.location.href);
        return url.searchParams.get('filter') ?? 'all';
    };

    const loadedKeys = new Set<string>();
    const cachedItems = new Map<string, string>();
    const getKey = (element: HTMLElement) => element.dataset.feedKey ?? element.dataset.projectSlug ?? '';
    let sentinelObserver: IntersectionObserver | null = null;
    let scrollHandler: (() => void) | null = null;
    let feedUpdatedHandler: (() => void) | null = null;
    const feedTabHandlers = new Map<HTMLButtonElement, () => void>();

    const seedKeys = () => {
        const items = Array.from(list.querySelectorAll<HTMLElement>('[data-feed-card], [data-feed-placeholder]'));
        items.forEach((item) => {
            const key = getKey(item);
            if (key) {
                loadedKeys.add(key);
            }
        });
    };
    seedKeys();

    const createPlaceholder = (card: HTMLElement) => {
        const placeholder = document.createElement('div');
        placeholder.className = 'feed-skeleton skeleton';
        placeholder.dataset.feedPlaceholder = '1';
        const height = Math.round(card.getBoundingClientRect().height);
        placeholder.style.height = `${Math.max(120, height)}px`;
        Object.entries(card.dataset).forEach(([key, value]) => {
            if (value === undefined) {
                return;
            }
            if (key === 'feedCard') {
                return;
            }
            placeholder.dataset[key] = value;
        });
        if (!placeholder.dataset.feedType) {
            placeholder.dataset.feedType = card.dataset.feedType ?? 'projects';
        }
        return placeholder;
    };

    const isVisibleItem = (item: HTMLElement) => !item.hidden && item.style.display !== 'none';

    const cacheCard = (card: HTMLElement) => {
        const key = getKey(card);
        if (!key) {
            return;
        }
        cachedItems.set(key, card.outerHTML);
    };

    const parseItem = (html: string) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild as HTMLElement | null;
    };

    const placeholderObserver =
        'IntersectionObserver' in window
            ? new IntersectionObserver(
                  (entries, observer) => {
                      entries.forEach((entry) => {
                          if (!entry.isIntersecting) {
                              return;
                          }
                          const placeholder = entry.target as HTMLElement;
                          const key = getKey(placeholder);
                          if (!key) {
                              return;
                          }
                          const html = cachedItems.get(key);
                          if (!html) {
                              return;
                          }
                          const element = parseItem(html);
                          if (!element) {
                              return;
                          }
                          observer.unobserve(placeholder);
                          placeholder.replaceWith(element);
                          hydrateFeedList(list);
                      });
                  },
                  { rootMargin: '320px 0px' },
              )
            : null;

    const observePlaceholder = (placeholder: HTMLElement) => {
        if (!placeholderObserver) {
            return;
        }
        if (placeholder.dataset.feedPlaceholderObserved === '1') {
            return;
        }
        placeholder.dataset.feedPlaceholderObserved = '1';
        placeholderObserver.observe(placeholder);
    };

    const compactOldItems = (stream: string) => {
        const cards = Array.from(
            list.querySelectorAll<HTMLElement>(`[data-feed-card][data-feed-type="${stream}"]`),
        ).filter(isVisibleItem);
        if (cards.length <= maxLive) {
            return;
        }
        const overflow = cards.length - maxLive;
        for (let i = 0; i < overflow; i += 1) {
            const card = cards[i];
            cacheCard(card);
            const placeholder = createPlaceholder(card);
            card.replaceWith(placeholder);
            observePlaceholder(placeholder);
        }
    };

    const insertItems = (items: string[], stream: string) => {
        if (!items.length) {
            return;
        }
        const fragment = document.createDocumentFragment();
        let inserted = 0;
        items.forEach((html) => {
            const element = parseItem(html);
            if (!element) {
                return;
            }
            const key = getKey(element);
            if (key && loadedKeys.has(key)) {
                return;
            }
            if (key) {
                loadedKeys.add(key);
            }
            fragment.appendChild(element);
            inserted += 1;
        });
        if (!inserted) {
            return;
        }
        sentinel.before(fragment);
        hydrateFeedList(list);
        compactOldItems(stream);
    };

    const updateMeta = (stream: string) => {
        const suffix = `${stream.charAt(0).toUpperCase()}${stream.slice(1)}`;
        const state = stateByStream[stream];
        list.dataset[`feedOffset${suffix}` as keyof DOMStringMap] = String(state.offset);
        list.dataset[`feedTotal${suffix}` as keyof DOMStringMap] = String(state.total);
    };

    const setLoading = (stream: string, state: boolean) => {
        const entry = stateByStream[stream];
        if (entry) {
            entry.isLoading = state;
        }
        if (loader) {
            const active = getActiveStream();
            const activeEntry = active ? stateByStream[active] : null;
            loader.hidden = !(activeEntry && activeEntry.isLoading);
        }
        updateEmptyState();
    };

    const updateEmptyState = () => {
        const emptyState = document.querySelector<HTMLElement>('[data-feed-empty]');
        if (!emptyState) {
            return;
        }
        const items = Array.from(document.querySelectorAll<HTMLElement>('[data-feed-card]'));
        const visible = items.some((item) => !item.hidden && item.style.display !== 'none');
        const active = getActiveStream();
        const activeEntry = active ? stateByStream[active] : null;
        const isLoading = activeEntry ? activeEntry.isLoading : false;
        emptyState.hidden = visible || isLoading;
    };

    const fetchItems = async (stream: string) => {
        const state = stateByStream[stream];
        if (!state || state.isLoading || !state.hasMore) {
            return;
        }
        setLoading(stream, true);
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('stream', stream);
        url.searchParams.set('offset', String(state.offset));
        url.searchParams.set('limit', String(pageSize));
        url.searchParams.set('filter', getActiveFilter());
        try {
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
            });
            if (!response.ok) {
                return;
            }
            const data = (await response.json()) as {
                items?: string[];
                next_offset?: number;
                total?: number;
            };
            const items = Array.isArray(data.items) ? data.items : [];
            insertItems(items, stream);
            state.offset = Number.isFinite(Number(data.next_offset))
                ? Number(data.next_offset)
                : state.offset + items.length;
            if (typeof data.total === 'number') {
                state.total = data.total;
            }
            state.hasMore = state.total === 0 ? items.length > 0 : state.offset < state.total;
            updateMeta(stream);
        } catch {
            // ignore
        } finally {
            setLoading(stream, false);
            updateEmptyState();
        }
    };

    const loadMore = () => {
        const stream = getActiveStream();
        if (!stream) {
            return;
        }
        void fetchItems(stream);
    };

    if ('IntersectionObserver' in window) {
        sentinelObserver = new IntersectionObserver(
            (entries) => {
                const [entry] = entries;
                if (!entry || !entry.isIntersecting) {
                    return;
                }
                loadMore();
            },
            { rootMargin: '0px 0px 520px 0px' },
        );
        sentinelObserver.observe(sentinel);
    } else {
        let ticking = false;
        const onScroll = () => {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(() => {
                const nearBottom =
                    window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 240;
                if (nearBottom) {
                    loadMore();
                }
                ticking = false;
            });
        };
        scrollHandler = onScroll;
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    const setupTabsForStream = () => {
        const tabs = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-feed-tab]'));
        tabs.forEach((tab) => {
            if (feedTabHandlers.has(tab)) {
                return;
            }
            const handler = () => {
                updateEmptyState();
            };
            feedTabHandlers.set(tab, handler);
            tab.addEventListener('click', handler);
        });
    };
    setupTabsForStream();

    feedUpdatedHandler = () => {
        setupTabsForStream();
        updateEmptyState();
    };
    document.addEventListener('feed:updated', feedUpdatedHandler);

    feedInfiniteCleanup = () => {
        if (feedUpdatedHandler) {
            document.removeEventListener('feed:updated', feedUpdatedHandler);
        }
        feedTabHandlers.forEach((handler, tab) => {
            tab.removeEventListener('click', handler);
        });
        feedTabHandlers.clear();
        sentinelObserver?.disconnect();
        placeholderObserver?.disconnect();
        if (scrollHandler) {
            window.removeEventListener('scroll', scrollHandler);
        }
    };
};

export const setupQaBlocks = () => {
    const blocks = Array.from(document.querySelectorAll<HTMLElement>('[data-qa-block]'));
    if (!blocks.length) {
        return;
    }

    blocks.forEach((block) => {
        if (block.dataset.qaBound === '1') {
            return;
        }
        block.dataset.qaBound = '1';
        const list = block.querySelector<HTMLElement>('[data-qa-list]');
        const tabs = Array.from(block.querySelectorAll<HTMLButtonElement>('[data-qa-tab]'));
        const moreButton = block.querySelector<HTMLButtonElement>('[data-qa-more]');
        const lessButton = block.querySelector<HTMLButtonElement>('[data-qa-less]');
        if (!list) {
            return;
        }

        const getItems = () => Array.from(list.querySelectorAll<HTMLElement>('[data-qa-item]'));
        const limit = Number(list.dataset.qaLimit ?? 0);
        let expanded = false;

        const sortItems = (mode: string, items: HTMLElement[]) => {
            const sorted = [...items];
            if (mode === 'hot') {
                sorted.sort((a, b) => {
                    const repliesA = Number(a.dataset.qaReplies ?? 0);
                    const repliesB = Number(b.dataset.qaReplies ?? 0);
                    const deltaA = Number(a.dataset.qaDelta ?? 0);
                    const deltaB = Number(b.dataset.qaDelta ?? 0);
                    return repliesB - repliesA || deltaB - deltaA;
                });
            } else if (mode === 'new') {
                sorted.sort((a, b) => {
                    const minutesA = Number(a.dataset.qaMinutes ?? 0);
                    const minutesB = Number(b.dataset.qaMinutes ?? 0);
                    return minutesA - minutesB;
                });
            } else {
                sorted.sort((a, b) => Number(a.dataset.qaOrder ?? 0) - Number(b.dataset.qaOrder ?? 0));
            }
            return sorted;
        };

        const applyVisibility = (items: HTMLElement[]) => {
            items.forEach((item, index) => {
                const shouldHide = !expanded && limit > 0 && index >= limit;
                item.classList.toggle('is-hidden', shouldHide);
            });
            if (moreButton) {
                moreButton.hidden = expanded || (limit > 0 && items.length <= limit);
            }
            if (lessButton) {
                lessButton.hidden = !expanded;
            }
        };

        const applyTab = (mode: string) => {
            const items = getItems();
            const sorted = sortItems(mode, items);
            sorted.forEach((item) => list.appendChild(item));
            tabs.forEach((tab) => {
                tab.classList.toggle('is-active', tab.dataset.qaTab === mode);
            });
            applyVisibility(sorted);
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.qaTab ?? 'questions';
                applyTab(mode);
            });
        });

        moreButton?.addEventListener('click', () => {
            expanded = true;
            applyVisibility(getItems());
        });

        lessButton?.addEventListener('click', () => {
            expanded = false;
            applyVisibility(getItems());
        });

        applyTab('questions');
    });
};
