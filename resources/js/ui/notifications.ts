import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';

let notificationsMenuBound = false;

export const setupNotificationsMenu = () => {
    const toggle = document.querySelector<HTMLButtonElement>('[data-notifications-toggle]');
    const menu = document.querySelector<HTMLElement>('[data-notifications-menu]');
    if (!toggle || !menu) {
        return;
    }
    if (notificationsMenuBound) {
        return;
    }
    if (menu.dataset.notificationsBound === '1') {
        return;
    }
    menu.dataset.notificationsBound = '1';
    notificationsMenuBound = true;

    const setOpen = (open: boolean) => {
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const toggleMenu = () => {
        setOpen(menu.hidden);
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleMenu();
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

export const setupNotificationReads = (root: ParentNode = document) => {
    const buttons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-notification-read]'));
    if (!buttons.length) {
        return;
    }

    buttons.forEach((button) => {
        if (button.dataset.notificationReadBound === '1') {
            return;
        }
        button.dataset.notificationReadBound = '1';
        button.addEventListener('click', async () => {
            const item = button.closest<HTMLElement>('[data-notification-item]');
            const id = item?.dataset.notificationId ?? '';
            if (!id || !csrfToken) {
                return;
            }
            button.disabled = true;
            try {
                const response = await fetch(`${appUrl}/notifications/${id}/read`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) {
                    throw new Error('failed');
                }
                if (item) {
                    item.dataset.notificationRead = '1';
                }
                button.remove();
                const menu = document.querySelector<HTMLElement>('[data-notifications-menu]');
                const menuItem = menu?.querySelector<HTMLElement>(`[data-notification-id="${id}"]`);
                menuItem?.remove();

                const countEl = document.querySelector<HTMLElement>('.notifications-menu__count');
                if (countEl) {
                    const current = Number(countEl.textContent ?? '0');
                    const next = Math.max(0, current - 1);
                    countEl.textContent = String(next);
                    countEl.hidden = next <= 0;
                    const badge = document.querySelector<HTMLElement>('.notification-badge');
                    if (badge) {
                        badge.hidden = next <= 0;
                    }
                    const empty = document.querySelector<HTMLElement>('.notifications-menu__empty');
                    if (empty) {
                        empty.hidden = next > 0;
                    }
                }

                toast.show(t('notification_mark_read', 'Marked as read.'));
            } catch {
                toast.show(t('notification_mark_read_failed', 'Unable to update notification.'));
                button.disabled = false;
            }
        });
    });
};
