import { t } from '../core/i18n';
import { normalizeQuery } from '../core/search';
import { toast } from '../core/toast';

export const setupProfileSettingsForm = () => {
    const form = document.querySelector<HTMLFormElement>('[data-profile-settings-form]');
    if (!form) {
        return;
    }
    if (form.dataset.profileSettingsLive === '1') {
        return;
    }
    form.dataset.profileSettingsLive = '1';
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        toast.show(t('profile_saved', 'Profile settings saved.'));
    });
};

export const setupProfileSettingsPage = () => {
    if (document.body.dataset.page !== 'profile-settings') {
        return;
    }

    const root = document.querySelector<HTMLElement>('[data-settings-root]');
    if (!root) {
        return;
    }

    const toastMessage = root.dataset.toastMessage ?? '';
    if (toastMessage) {
        toast.show(toastMessage);
        delete root.dataset.toastMessage;
    }

    const navButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-settings-nav]'));
    const panels = Array.from(root.querySelectorAll<HTMLElement>('[data-settings-section]'));
    const searchInput = root.querySelector<HTMLInputElement>('[data-settings-search]');
    const emptyState = root.querySelector<HTMLElement>('[data-settings-empty]');
    const title = root.querySelector<HTMLElement>('[data-settings-title]');

    if (!navButtons.length || !panels.length) {
        return;
    }

    const panelBySection = new Map<string, HTMLElement>();
    panels.forEach((panel) => {
        const section = panel.dataset.settingsSection ?? '';
        if (section) {
            panelBySection.set(section, panel);
        }
    });

    const panelSearchText = new Map<HTMLElement, string>();
    panels.forEach((panel) => {
        panelSearchText.set(panel, normalizeQuery(panel.textContent ?? ''));
    });

    const getScrollOffset = () => {
        const topbar = document.querySelector<HTMLElement>('.topbar');
        const topbarHeight = topbar?.getBoundingClientRect().height ?? 0;
        const cookieOffsetRaw = window
            .getComputedStyle(document.body)
            .getPropertyValue('--cookie-banner-offset')
            .trim();
        const cookieOffset = cookieOffsetRaw ? Number.parseFloat(cookieOffsetRaw) : 0;
        return topbarHeight + (Number.isFinite(cookieOffset) ? cookieOffset : 0) + 12;
    };

    const setActive = (section: string) => {
        navButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.settingsNav === section);
        });
        if (title) {
            const label = navButtons
                .find((button) => button.dataset.settingsNav === section)
                ?.textContent?.trim();
            if (label) {
                title.textContent = label;
            }
        }
    };

    const scrollToSection = (section: string) => {
        const panel = panelBySection.get(section);
        if (!panel) {
            return;
        }
        const offset = getScrollOffset();
        const top = panel.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    };

    const updateUrl = (section: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('section', section);
        window.history.replaceState({}, '', url.toString());
    };

    navButtons.forEach((button) => {
        if (button.dataset.settingsNavBound === '1') {
            return;
        }
        button.dataset.settingsNavBound = '1';
        button.addEventListener('click', () => {
            const section = button.dataset.settingsNav ?? '';
            if (!section) {
                return;
            }
            setActive(section);
            scrollToSection(section);
            updateUrl(section);
        });
    });

    let isFiltering = false;
    const applyFilter = (query: string) => {
        if (!query) {
            isFiltering = false;
            panels.forEach((panel) => {
                panel.hidden = false;
            });
            navButtons.forEach((button) => {
                button.hidden = false;
            });
            if (emptyState) {
                emptyState.hidden = true;
            }
            const current = navButtons.find((button) => button.classList.contains('is-active'))?.dataset.settingsNav;
            if (current && panelBySection.has(current)) {
                return;
            }
            const first = navButtons[0]?.dataset.settingsNav;
            if (first) {
                setActive(first);
            }
            return;
        }

        isFiltering = true;
        let visibleCount = 0;
        panels.forEach((panel) => {
            const text = panelSearchText.get(panel) ?? '';
            const matches = text.includes(query);
            panel.hidden = !matches;
            if (matches) {
                visibleCount += 1;
            }
        });

        navButtons.forEach((button) => {
            const section = button.dataset.settingsNav ?? '';
            const panel = section ? panelBySection.get(section) : null;
            button.hidden = panel ? panel.hidden : true;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }

        const firstVisible = panels.find((panel) => !panel.hidden);
        if (firstVisible) {
            const section = firstVisible.dataset.settingsSection ?? '';
            if (section) {
                setActive(section);
            }
        }
    };

    if (searchInput) {
        if (searchInput.dataset.settingsSearchBound !== '1') {
            searchInput.dataset.settingsSearchBound = '1';
            searchInput.addEventListener('input', () => {
                applyFilter(normalizeQuery(searchInput.value));
            });
        }
    }

    const url = new URL(window.location.href);
    const initialSection = url.searchParams.get('section');
    if (initialSection && panelBySection.has(initialSection)) {
        setActive(initialSection);
        window.setTimeout(() => scrollToSection(initialSection), 0);
    } else {
        const initial =
            navButtons.find((button) => button.classList.contains('is-active'))?.dataset.settingsNav ??
            navButtons[0]?.dataset.settingsNav;
        if (initial) {
            setActive(initial);
        }
    }

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                if (isFiltering) {
                    return;
                }
                const visible = entries.filter((entry) => entry.isIntersecting);
                if (!visible.length) {
                    return;
                }
                visible.sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                const section = (visible[0].target as HTMLElement).dataset.settingsSection ?? '';
                if (section) {
                    setActive(section);
                }
            },
            { rootMargin: '-30% 0px -50% 0px', threshold: [0.1, 0.4, 0.7] },
        );
        panels.forEach((panel) => observer.observe(panel));
    }
};
