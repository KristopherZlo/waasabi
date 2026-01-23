let tabsResizeHandler: (() => void) | null = null;

export const setupTabs = () => {
    const tabs = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-tab]'));
    if (!tabs.length) {
        return;
    }

    const measurePanels = () => {
        const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));
        const container = panels[0]?.parentElement;
        if (!container || !panels.length) {
            return;
        }
        if (container.dataset.tabAutoheight === '1') {
            container.style.minHeight = '';
            return;
        }
        let maxHeight = 0;
        panels.forEach((panel) => {
            const wasActive = panel.classList.contains('is-active');
            if (!wasActive) {
                panel.classList.add('is-active');
                panel.style.position = 'absolute';
                panel.style.visibility = 'hidden';
                panel.style.pointerEvents = 'none';
                panel.style.display = 'block';
            }
            const height = panel.scrollHeight;
            maxHeight = Math.max(maxHeight, height);
            if (!wasActive) {
                panel.style.display = '';
                panel.style.position = '';
                panel.style.visibility = '';
                panel.style.pointerEvents = '';
                panel.classList.remove('is-active');
            }
        });
        container.style.minHeight = `${maxHeight}px`;
    };

    const activate = (name: string) => {
        const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));
        tabs.forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.tab === name);
        });
        panels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.tabPanel === name);
        });
        measurePanels();
    };

    const url = new URL(window.location.href);
    const initialTab = url.searchParams.get('tab');
    if (initialTab && tabs.some((tab) => tab.dataset.tab === initialTab)) {
        activate(initialTab);
    }
    measurePanels();

    tabs.forEach((tab) => {
        if (tab.dataset.tabBound === '1') {
            return;
        }
        tab.dataset.tabBound = '1';
        tab.addEventListener('click', () => {
            if (tab.dataset.tab) {
                activate(tab.dataset.tab);
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('tab', tab.dataset.tab);
                window.history.replaceState({}, '', nextUrl.toString());
            }
        });
    });

    if (tabsResizeHandler) {
        window.removeEventListener('resize', tabsResizeHandler);
    }
    tabsResizeHandler = measurePanels;
    window.addEventListener('resize', measurePanels);
};
