type ActionMenuEntry = { container: HTMLElement; toggle: HTMLElement; menu: HTMLElement };

const actionMenuEntries: ActionMenuEntry[] = [];
let actionMenuHandlersBound = false;

const setActionMenuOpen = (entry: { toggle: HTMLElement; menu: HTMLElement }, open: boolean) => {
    entry.menu.hidden = !open;
    entry.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
};

const closeAllActionMenus = () => {
    actionMenuEntries.forEach((entry) => setActionMenuOpen(entry, false));
};

export const resetActionMenus = () => {
    closeAllActionMenus();
    actionMenuEntries.length = 0;
};

export const bindActionMenus = (root: ParentNode = document) => {
    const containers = Array.from(root.querySelectorAll<HTMLElement>('[data-action-menu-container]'));
    if (!containers.length) {
        return;
    }

    containers.forEach((container) => {
        if (container.dataset.actionMenuBound === '1') {
            return;
        }
        const toggle = container.querySelector<HTMLElement>('[data-action-menu-toggle]');
        const menu = container.querySelector<HTMLElement>('[data-action-menu]');
        if (!toggle || !menu) {
            return;
        }
        container.dataset.actionMenuBound = '1';
        const entry = { container, toggle, menu };
        actionMenuEntries.push(entry);

        entry.toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = entry.menu.hidden;
            closeAllActionMenus();
            setActionMenuOpen(entry, willOpen);
        });

        entry.menu.addEventListener('click', (event) => {
            const target = event.target as HTMLElement | null;
            if (target?.closest('[data-action-menu-close]')) {
                setActionMenuOpen(entry, false);
            }
        });
    });
};

export const setupActionMenus = () => {
    bindActionMenus();
    if (actionMenuHandlersBound) {
        return;
    }
    actionMenuHandlersBound = true;

    document.addEventListener(
        'pointerdown',
        (event) => {
            const target = event.target as Node | null;
            if (!target) {
                closeAllActionMenus();
                return;
            }
            const inside = actionMenuEntries.some((entry) => entry.container.contains(target));
            if (!inside) {
                closeAllActionMenus();
            }
        },
        true,
    );

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllActionMenus();
        }
    });
};
