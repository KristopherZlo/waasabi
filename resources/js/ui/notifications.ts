import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';

let notificationsMenuBound = false;

const updateMenuUnreadCount = (next: number) => {
    const countEl = document.querySelector<HTMLElement>('.notifications-menu__count');
    if (countEl) {
        countEl.textContent = String(next);
        countEl.hidden = next <= 0;
    }
    const badge = document.querySelector<HTMLElement>('.notification-badge');
    if (badge) {
        badge.hidden = next <= 0;
    }
    const empty = document.querySelector<HTMLElement>('.notifications-menu__empty');
    if (empty) {
        empty.hidden = next > 0;
    }
};

const updateNotificationsPageState = (root: HTMLElement) => {
    const newList = root.querySelector<HTMLElement>('[data-notifications-list="new"]');
    const readList = root.querySelector<HTMLElement>('[data-notifications-list="read"]');
    const newCount = newList ? newList.querySelectorAll('[data-notification-item]').length : 0;
    const readCount = readList ? readList.querySelectorAll('[data-notification-item]').length : 0;
    const hasTotalNew = typeof root.dataset.notificationsTotalNew !== 'undefined';
    const hasTotalRead = typeof root.dataset.notificationsTotalRead !== 'undefined';
    const totalNew = hasTotalNew ? Number(root.dataset.notificationsTotalNew ?? '0') : newCount;
    const totalRead = hasTotalRead ? Number(root.dataset.notificationsTotalRead ?? '0') : readCount;

    const newCountEl = root.querySelector<HTMLElement>('[data-notifications-count="new"]');
    if (newCountEl) {
        newCountEl.textContent = String(Number.isNaN(totalNew) ? newCount : totalNew);
    }
    const readCountEl = root.querySelector<HTMLElement>('[data-notifications-count="read"]');
    if (readCountEl) {
        readCountEl.textContent = String(Number.isNaN(totalRead) ? readCount : totalRead);
    }

    const newEmpty = root.querySelector<HTMLElement>('[data-notifications-empty="new"]');
    if (newEmpty) {
        newEmpty.hidden = newCount > 0;
    }
    const readEmpty = root.querySelector<HTMLElement>('[data-notifications-empty="read"]');
    if (readEmpty) {
        readEmpty.hidden = readCount > 0;
    }

    const markAll = root.querySelector<HTMLButtonElement>('[data-notifications-mark-all]');
    if (markAll) {
        markAll.disabled = newCount <= 0;
    }
};

const moveNotificationToRead = (item: HTMLElement, root: HTMLElement) => {
    const readList = root.querySelector<HTMLElement>('[data-notifications-list="read"]');
    if (!readList) {
        return;
    }
    item.dataset.notificationRead = '1';
    if (typeof root.dataset.notificationsTotalNew !== 'undefined') {
        const totalNew = Math.max(0, Number(root.dataset.notificationsTotalNew ?? '0') - 1);
        root.dataset.notificationsTotalNew = String(totalNew);
    }
    if (typeof root.dataset.notificationsTotalRead !== 'undefined') {
        const totalRead = Number(root.dataset.notificationsTotalRead ?? '0') + 1;
        root.dataset.notificationsTotalRead = String(totalRead);
    }
    readList.prepend(item);
};

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
    const buttons = Array.from(root.querySelectorAll<HTMLButtonElement>('button[data-notification-read]'));
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
                const current = Number(countEl?.textContent ?? '0');
                updateMenuUnreadCount(Math.max(0, current - 1));

                const pageRoot = document.querySelector<HTMLElement>('[data-notifications-root]');
                if (item && pageRoot) {
                    moveNotificationToRead(item, pageRoot);
                    updateNotificationsPageState(pageRoot);
                }

                toast.show(t('notification_mark_read', 'Marked as read.'));
            } catch {
                toast.show(t('notification_mark_read_failed', 'Unable to update notification.'));
                button.disabled = false;
            }
        });
    });
};

export const setupNotificationsPage = () => {
    const root = document.querySelector<HTMLElement>('[data-notifications-root]');
    if (!root) {
        return;
    }
    if (root.dataset.notificationsBound === '1') {
        return;
    }
    root.dataset.notificationsBound = '1';

    updateNotificationsPageState(root);

    const markAllButton = root.querySelector<HTMLButtonElement>('[data-notifications-mark-all]');
    if (!markAllButton) {
        return;
    }

    markAllButton.addEventListener('click', async () => {
        if (markAllButton.disabled || !csrfToken) {
            return;
        }
        const unreadItems = Array.from(
            root.querySelectorAll<HTMLElement>('[data-notification-item][data-notification-read="0"]'),
        );
        if (!unreadItems.length) {
            markAllButton.disabled = true;
            return;
        }

        markAllButton.disabled = true;
        try {
            const response = await fetch(`${appUrl}/notifications/read-all`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });
            if (!response.ok) {
                throw new Error('failed');
            }

            unreadItems.reverse().forEach((item) => {
                const button = item.querySelector<HTMLButtonElement>('[data-notification-read]');
                button?.remove();
                moveNotificationToRead(item, root);
            });

            const menu = document.querySelector<HTMLElement>('[data-notifications-menu]');
            menu?.querySelectorAll<HTMLElement>('[data-notification-id]').forEach((node) => node.remove());
            updateMenuUnreadCount(0);

            updateNotificationsPageState(root);
            toast.show(t('notification_mark_all_read', 'All notifications marked as read.'));
        } catch {
            toast.show(t('notification_mark_all_failed', 'Unable to mark notifications as read.'));
            markAllButton.disabled = false;
        }
    });
};
