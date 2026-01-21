import { t } from '../core/i18n';
import { toast } from '../core/toast';

const actionSelectors = [
    '[data-action]',
    '[data-report-open]',
    '[data-report-close]',
    '[data-report-cancel]',
    '[data-auth]',
    '[data-share]',
    '[data-comment-share]',
    '[data-admin-toggle]',
    '[data-admin-queue]',
    '[data-admin-hide]',
    '[data-admin-restore]',
    '[data-admin-delete]',
    '[data-admin-nsfw]',
    '[data-admin-flag]',
    '[data-author-delete]',
    '[data-reaction]',
    '[data-follow-button]',
    '[data-comment-submit]',
    '[data-comment-reply]',
    '[data-review-submit]',
    '[data-review-vote]',
    '[data-comment-vote]',
    '[data-notification-read]',
    '[data-badge-submit]',
    '[data-badge-close]',
    '[data-badge-revoke-close]',
    '[data-profile-action]',
    '[data-profile-banner-change]',
    '[data-profile-avatar-trigger]',
    '[data-profile-media-choose]',
    '[data-profile-media-remove]',
    '[data-profile-media-apply]',
    '[data-profile-media-close]',
    '[data-profile-media-cancel]',
    '[data-settings-save]',
].join(',');

const shouldBlockClick = (target: HTMLElement | null) => {
    if (!target) {
        return false;
    }
    return Boolean(target.closest(actionSelectors));
};

export const setupBannedGuard = () => {
    if (document.body.dataset.banned !== '1') {
        return;
    }

    const message = t('user_banned', 'Your account is banned.');
    const notify = () => toast.show(message);

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target as HTMLElement | null;
            if (!shouldBlockClick(target)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            notify();
        },
        true,
    );

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target as HTMLFormElement | null;
            if (!form) {
                return;
            }
            const method = (form.getAttribute('method') ?? 'get').toLowerCase();
            if (method === 'get') {
                return;
            }
            const action = form.getAttribute('action') ?? '';
            if (action.endsWith('/logout') || action.includes('/logout?')) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            notify();
        },
        true,
    );
};
