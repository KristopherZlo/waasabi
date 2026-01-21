import { appUrl, csrfToken } from '../core/config';
import { t, tFormat } from '../core/i18n';
import { getReadingState, setReadingState } from '../core/storage';
import type { ReadingState } from '../core/types';
import { buildInlineCarousels, setupCarousels } from '../ui/carousels';

let readingExperienceCleanup: (() => void) | null = null;

const syncReadingProgress = async (slug: string, state: ReadingState) => {
    if (!csrfToken) {
        return;
    }
    try {
        await fetch(`${appUrl}/reading-progress`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                post_id: slug,
                percent: state.percent,
                anchor: state.anchor,
            }),
        });
    } catch {
        // Ignore network errors in demo.
    }
};

export const updateCardsProgress = () => {
    const cards = Array.from(document.querySelectorAll<HTMLElement>('[data-feed-card]'));
    if (!cards.length) {
        return;
    }
    const isReadLaterPage = document.body.dataset.page === 'read-later';

    cards.forEach((card) => {
        const slug = card.dataset.projectSlug ?? '';
        if (!slug) {
            return;
        }
        const state = getReadingState(slug);
        const readLabel = card.querySelector<HTMLElement>('[data-read-progress-label]');
        const readProgress = card.querySelector<HTMLElement>('[data-read-progress]');
        const cta = card.querySelector<HTMLAnchorElement>('[data-read-cta]');

        const shouldContinue = !!state && state.percent >= 5 && state.percent <= 95;

        if (readLabel) {
            readLabel.hidden = !shouldContinue;
        }

        if (cta) {
            cta.textContent = shouldContinue ? t('read_continue', 'Continue') : t('read_start', 'Read');
        }

        if (readProgress) {
            if (state && state.percent > 0) {
                readProgress.textContent = tFormat('progress', 'Progress: :percent%', { percent: state.percent });
                readProgress.hidden = !isReadLaterPage;
            } else if (isReadLaterPage) {
                readProgress.textContent = tFormat('progress', 'Progress: :percent%', { percent: 0 });
                readProgress.hidden = false;
            } else {
                readProgress.hidden = true;
            }
        }
    });
};

export const setupReadingExperience = () => {
    if (readingExperienceCleanup) {
        readingExperienceCleanup();
        readingExperienceCleanup = null;
    }
    const article = document.querySelector<HTMLElement>('[data-reading-article]');
    if (!article) {
        return;
    }
    if (article.dataset.readingBound === '1') {
        return;
    }
    article.dataset.readingBound = '1';

    buildInlineCarousels(article);
    setupCarousels(article);

    const slug = article.dataset.projectSlug ?? 'project';
    const toc = document.querySelector<HTMLElement>('[data-toc]');
    const tocList = document.querySelector<HTMLElement>('[data-toc-list]');
    const tocToggle = document.querySelector<HTMLButtonElement>('[data-toc-toggle]');
    const progressBar = document.querySelector<HTMLElement>('[data-reading-progress]');
    const banner = document.querySelector<HTMLElement>('[data-reading-banner]');
    const continueButton = document.querySelector<HTMLButtonElement>('[data-reading-continue]');
    const restartButton = document.querySelector<HTMLButtonElement>('[data-reading-restart]');

    const headings = Array.from(article.querySelectorAll<HTMLElement>('h2, h3'));
    const links = new Map<string, HTMLAnchorElement>();

    if (tocList) {
        tocList.innerHTML = '';

        headings.forEach((heading, index) => {
            if (!heading.id) {
                heading.id = `section-${index + 1}`;
            }
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent ?? '';
            link.className = `toc-link ${heading.tagName === 'H3' ? 'toc-h3' : ''}`;
            link.dataset.tocId = heading.id;
            links.set(heading.id, link);
            tocList.appendChild(link);
        });

        if (headings.length) {
            const firstLink = links.get(headings[0].id);
            if (firstLink) {
                firstLink.classList.add('is-active');
            }
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }
                        const id = (entry.target as HTMLElement).id;
                        links.forEach((link) => link.classList.remove('is-active'));
                        const active = links.get(id);
                        if (active) {
                            active.classList.add('is-active');
                        }
                    });
                },
                {
                    rootMargin: '-20% 0px -70% 0px',
                    threshold: [0, 1],
                },
            );

            headings.forEach((heading) => observer.observe(heading));
        }

        tocList.addEventListener('click', () => {
            if (toc && toc.classList.contains('is-open')) {
                toc.classList.remove('is-open');
            }
        });
    }

    if (toc && tocToggle) {
        tocToggle.addEventListener('click', () => {
            toc.classList.toggle('is-open');
        });
    }

    const getScrollPercent = () => {
        const start = article.offsetTop;
        const end = start + article.scrollHeight - window.innerHeight;
        const current = window.scrollY;
        if (end <= start) {
            return 100;
        }
        const progress = (current - start) / (end - start);
        return Math.round(Math.min(Math.max(progress, 0), 1) * 100);
    };

    const getAnchor = () => {
        if (!headings.length) {
            return null;
        }
        const offset = window.scrollY + 140;
        let current = headings[0];
        for (const heading of headings) {
            const top = heading.getBoundingClientRect().top + window.scrollY;
            if (top <= offset) {
                current = heading;
            } else {
                break;
            }
        }
        return current.id || null;
    };

    const updateProgress = () => {
        if (!progressBar) {
            return;
        }
        const percent = getScrollPercent();
        progressBar.style.width = `${percent}%`;
    };

    let saveTimer: number | undefined;
    let lastSyncedAt = 0;
    const scheduleSave = () => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            const percent = getScrollPercent();
            const anchor = getAnchor();
            const state = {
                percent,
                anchor,
                scroll: window.scrollY,
                updatedAt: Date.now(),
            };
            setReadingState(slug, state);
            if (Date.now() - lastSyncedAt > 8000) {
                lastSyncedAt = Date.now();
                syncReadingProgress(slug, state);
            }
            updateCardsProgress();
        }, 2200);
    };

    const persistNow = () => {
        const percent = getScrollPercent();
        const anchor = getAnchor();
        const state = {
            percent,
            anchor,
            scroll: window.scrollY,
            updatedAt: Date.now(),
        };
        setReadingState(slug, state);
        syncReadingProgress(slug, state);
    };

    const stored = getReadingState(slug);
    if (banner && stored && stored.percent >= 5 && stored.percent <= 95) {
        banner.hidden = false;
        if (continueButton) {
            continueButton.addEventListener('click', () => {
                if (stored.anchor) {
                    const target = document.getElementById(stored.anchor);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return;
                    }
                }
                window.scrollTo({ top: stored.scroll ?? 0, behavior: 'smooth' });
            });
        }
        if (restartButton) {
            restartButton.addEventListener('click', () => {
                window.scrollTo({ top: article.offsetTop, behavior: 'smooth' });
            });
        }
    }

    let ticking = false;
    const onScroll = () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(() => {
                updateProgress();
                ticking = false;
            });
        }
        scheduleSave();
    };

    updateProgress();
    updateCardsProgress();

    const onResize = () => updateProgress();
    const onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            persistNow();
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize);
    window.addEventListener('beforeunload', persistNow);
    document.addEventListener('visibilitychange', onVisibilityChange);

    readingExperienceCleanup = () => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onResize);
        window.removeEventListener('beforeunload', persistNow);
        document.removeEventListener('visibilitychange', onVisibilityChange);
    };
};
