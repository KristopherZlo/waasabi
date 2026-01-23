import { t } from '../core/i18n';
import type { PageSettings } from '../core/types';
import { getSettings, setSettings } from '../core/storage';
import { toast } from '../core/toast';

let settingsModalBound = false;

export const setupSettingsModal = () => {
    const modal = document.querySelector<HTMLElement>('[data-settings-modal]');
    const panel = document.querySelector<HTMLElement>('[data-settings-panel]');
    const openButton = document.querySelector<HTMLElement>('[data-settings-open]');
    const closeButton = document.querySelector<HTMLElement>('[data-settings-close]');
    const saveButton = document.querySelector<HTMLButtonElement>('[data-settings-save]');
    if (!modal || !openButton || !panel) {
        return;
    }
    if (settingsModalBound) {
        return;
    }
    if (modal.dataset.settingsModalBound === '1') {
        return;
    }
    modal.dataset.settingsModalBound = '1';
    settingsModalBound = true;

    const applyTheme = (theme: PageSettings['theme']) => {
        if (theme === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
    };

    const applyFeedView = (view: PageSettings['feedView']) => {
        document.body.dataset.feedView = view;
    };

    const syncOptionClasses = () => {
        const options = panel.querySelectorAll<HTMLElement>('.settings-option');
        options.forEach((option) => {
            const input = option.querySelector<HTMLInputElement>('input');
            if (!input) {
                return;
            }
            option.classList.toggle('is-active', input.checked);
        });
    };

    const applySettingsToInputs = (settings: PageSettings) => {
        const publicationInputs = panel.querySelectorAll<HTMLInputElement>('input[data-setting="publications"]');
        publicationInputs.forEach((input) => {
            input.checked = settings.publications.includes(input.value);
        });

        const feedInputs = panel.querySelectorAll<HTMLInputElement>('input[data-setting="feed_view"]');
        feedInputs.forEach((input) => {
            input.checked = input.value === settings.feedView;
        });

        const themeInputs = panel.querySelectorAll<HTMLInputElement>('input[data-setting="theme"]');
        themeInputs.forEach((input) => {
            input.checked = input.value === settings.theme;
        });

        syncOptionClasses();
    };

    const buildSettingsFromInputs = (): PageSettings => {
        const publications = Array.from(
            panel.querySelectorAll<HTMLInputElement>('input[data-setting="publications"]:checked'),
        )
            .map((input) => input.value)
            .filter(Boolean);

        const feedView =
            panel.querySelector<HTMLInputElement>('input[data-setting="feed_view"]:checked')?.value ?? 'classic';
        const theme = panel.querySelector<HTMLInputElement>('input[data-setting="theme"]:checked')?.value ?? 'system';

        return {
            theme: theme === 'dark' || theme === 'light' ? theme : 'system',
            feedView: feedView === 'compact' ? 'compact' : 'classic',
            publications: publications.length ? publications : ['en'],
        };
    };

    const applySettings = (settings: PageSettings) => {
        applyTheme(settings.theme);
        applyFeedView(settings.feedView);
        applySettingsToInputs(settings);
    };

    const open = () => {
        modal.hidden = false;
        document.body.classList.add('is-locked');
    };

    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('is-locked');
    };

    const initialSettings = getSettings();
    applySettings(initialSettings);

    panel.addEventListener('change', () => {
        const settings = buildSettingsFromInputs();
        applySettings(settings);
    });

    saveButton?.addEventListener('click', () => {
        const settings = buildSettingsFromInputs();
        setSettings(settings);
        applySettings(settings);
        toast.show(t('settings_saved', 'Settings saved.'));
        close();
    });

    openButton.addEventListener('click', () => open());
    closeButton?.addEventListener('click', () => close());
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
};
