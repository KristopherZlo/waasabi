import { t, tFormat } from '../core/i18n';
import { toast } from '../core/toast';

export const setupAuthButtons = () => {
    const buttons = document.querySelectorAll<HTMLAnchorElement>('[data-auth]');
    if (!buttons.length) {
        return;
    }
    buttons.forEach((button) => {
        if (button.dataset.authBound === '1') {
            return;
        }
        button.dataset.authBound = '1';
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const provider = button.dataset.auth ?? t('provider_fallback', 'provider');
            toast.show(tFormat('auth_soon', 'Sign in with :provider is coming soon.', { provider }));
        });
    });
};
