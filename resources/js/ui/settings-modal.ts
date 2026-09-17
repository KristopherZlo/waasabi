import { t } from '../core/i18n';
import { getSettings, setSettings } from '../core/storage';
import type { PageSettings } from '../core/types';
import { toast } from '../core/toast';

const applyTheme = (theme: PageSettings['theme']) => {
    if (theme === 'system') {
        document.documentElement.removeAttribute('data-theme');
        return;
    }

    document.documentElement.setAttribute('data-theme', theme);
};

const applyFeedView = (view: PageSettings['feedView']) => {
    document.body.dataset.feedView = view;
};

export const setupSettingsModal = () => {
    const initialSettings = getSettings();
    applyTheme(initialSettings.theme);
    applyFeedView(initialSettings.feedView);

    const panel = document.querySelector<HTMLElement>('[data-device-settings]');
    if (!panel || panel.dataset.settingsBound === '1') {
        return;
    }
    panel.dataset.settingsBound = '1';

    const syncOptionClasses = () => {
        panel.querySelectorAll<HTMLElement>('.settings-option').forEach((option) => {
            const input = option.querySelector<HTMLInputElement>('input');
            if (input) {
                option.classList.toggle('is-active', input.checked);
            }
        });
    };

    const applySettingsToInputs = (settings: PageSettings) => {
        panel.querySelectorAll<HTMLInputElement>('input[data-setting="publications"]').forEach((input) => {
            input.checked = settings.publications.includes(input.value);
        });
        panel.querySelectorAll<HTMLInputElement>('input[data-setting="feed_view"]').forEach((input) => {
            input.checked = input.value === settings.feedView;
        });
        panel.querySelectorAll<HTMLInputElement>('input[data-setting="theme"]').forEach((input) => {
            input.checked = input.value === settings.theme;
        });
        syncOptionClasses();
    };

    const buildSettingsFromInputs = (): PageSettings => {
        const publications = Array.from(
            panel.querySelectorAll<HTMLInputElement>('input[data-setting="publications"]:checked'),
        ).map((input) => input.value);
        const feedView = panel.querySelector<HTMLInputElement>('input[data-setting="feed_view"]:checked')?.value;
        const theme = panel.querySelector<HTMLInputElement>('input[data-setting="theme"]:checked')?.value;

        return {
            theme: theme === 'dark' || theme === 'light' ? theme : 'system',
            feedView: feedView === 'compact' ? 'compact' : 'classic',
            publications: publications.length ? publications : ['en'],
        };
    };

    applySettingsToInputs(initialSettings);

    panel.addEventListener('change', () => {
        const settings = buildSettingsFromInputs();
        applyTheme(settings.theme);
        applyFeedView(settings.feedView);
        syncOptionClasses();
    });

    panel.querySelector<HTMLButtonElement>('[data-settings-save]')?.addEventListener('click', () => {
        const settings = buildSettingsFromInputs();
        setSettings(settings);
        applyTheme(settings.theme);
        applyFeedView(settings.feedView);
        toast.show(t('settings_saved', 'Settings saved.'));
    });
};
