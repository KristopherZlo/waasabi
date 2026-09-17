export const setupTabs = () => {
    const tabs = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-tab]'));
    const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));
    if (!tabs.length || !panels.length) {
        return;
    }

    const tabList = document.querySelector<HTMLElement>('[data-tabs]') ?? tabs[0].parentElement;
    tabList?.setAttribute('role', 'tablist');
    tabList?.setAttribute('aria-orientation', 'horizontal');

    tabs.forEach((tab) => {
        const name = tab.dataset.tab ?? '';
        const panel = panels.find((candidate) => candidate.dataset.tabPanel === name);
        tab.id ||= `tab-${name}`;
        tab.setAttribute('role', 'tab');
        if (panel) {
            panel.id ||= `tab-panel-${name}`;
            tab.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id);
        }
    });

    const activate = (name: string, moveFocus = false) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.tab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
            if (active && moveFocus) {
                tab.focus();
            }
        });
        panels.forEach((panel) => {
            const active = panel.dataset.tabPanel === name;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
        if (document.body.dataset.page === 'project') {
            document.body.dataset.projectTab = name;
        }
    };

    const url = new URL(window.location.href);
    const requested = url.searchParams.get('tab');
    const initial = tabs.some((tab) => tab.dataset.tab === requested)
        ? requested!
        : (tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.tab ?? tabs[0].dataset.tab ?? '');
    activate(initial);

    tabs.forEach((tab, index) => {
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
        tab.addEventListener('keydown', (event) => {
            const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
            if (!keys.includes(event.key)) {
                return;
            }
            event.preventDefault();
            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? tabs.length - 1
                  : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            const nextName = tabs[nextIndex]?.dataset.tab;
            if (nextName) {
                activate(nextName, true);
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('tab', nextName);
                window.history.replaceState({}, '', nextUrl.toString());
            }
        });
    });
};
