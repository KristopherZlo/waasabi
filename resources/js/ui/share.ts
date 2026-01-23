import { t } from '../core/i18n';
import { toast } from '../core/toast';

export const setupShareButtons = () => {
    const buttons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-share]'));
    if (!buttons.length) {
        return;
    }
    buttons.forEach((button) => {
        if (button.dataset.shareBound === '1') {
            return;
        }
        button.dataset.shareBound = '1';
        button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl ?? window.location.href;
            const title = document.title;
            if (navigator.share) {
                try {
                    await navigator.share({ title, url });
                    return;
                } catch {
                    // fall back to copy
                }
            }
            try {
                await navigator.clipboard.writeText(url);
                toast.show(t('share_copied', 'Link copied.'));
            } catch {
                toast.show(t('share_failed', 'Unable to share.'));
            }
        });
    });
};
