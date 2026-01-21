import { appUrl } from '../core/config';

type SpaDeps = {
    hydratePage: () => void;
    resetActionMenus: () => void;
};

let spaNavigationBound = false;
let spaNavigationPending = false;
let spaDeps: SpaDeps | null = null;

export const registerSpaDependencies = (deps: SpaDeps) => {
    spaDeps = deps;
};

const getSpaBasePath = () => {
    if (!appUrl) {
        return '';
    }
    try {
        const base = new URL(appUrl, window.location.origin);
        const path = base.pathname.replace(/\/+$/, '');
        return path === '/' ? '' : path;
    } catch {
        return '';
    }
};

const normalizeSpaPath = (url: URL) => {
    const basePath = getSpaBasePath();
    const rawPath = url.pathname.replace(/\/+$/, '') || '/';
    if (basePath && rawPath.startsWith(basePath)) {
        const sliced = rawPath.slice(basePath.length);
        return sliced === '' ? '/' : sliced;
    }
    return rawPath;
};

const updateNavActiveState = (url: URL) => {
    const path = normalizeSpaPath(url);
    const matches = {
        feed: path === '/',
        readLater: path === '/read-later',
        publish: path === '/publish',
        showcase: path === '/showcase',
        notifications: path === '/notifications',
        profile: path === '/profile' || path.startsWith('/profile/'),
        login: path === '/login',
        register: path === '/register',
    };

    const navLinks = Array.from(document.querySelectorAll<HTMLAnchorElement>('.nav a'));
    navLinks.forEach((link) => {
        let linkUrl: URL;
        try {
            linkUrl = new URL(link.href, window.location.origin);
        } catch {
            link.classList.remove('is-active');
            link.removeAttribute('aria-current');
            return;
        }
        const linkPath = normalizeSpaPath(linkUrl);
        let isActive = false;
        if (linkPath === '/') {
            isActive = matches.feed;
        } else if (linkPath === '/read-later') {
            isActive = matches.readLater;
        } else if (linkPath === '/publish') {
            isActive = matches.publish;
        } else if (linkPath === '/showcase') {
            isActive = matches.showcase;
        } else if (linkPath === '/notifications') {
            isActive = matches.notifications;
        } else if (linkPath === '/profile' || linkPath.startsWith('/profile/')) {
            isActive = matches.profile;
        } else if (linkPath === '/login') {
            isActive = matches.login;
        } else if (linkPath === '/register') {
            isActive = matches.register;
        }
        link.classList.toggle('is-active', isActive);
        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
};

const isSpaEligibleUrl = (url: URL) => {
    if (url.origin !== window.location.origin) {
        return false;
    }
    const path = normalizeSpaPath(url);
    if (path === '/') {
        return true;
    }
    if (path === '/profile' || path.startsWith('/profile/')) {
        return true;
    }
    if (path.startsWith('/projects/')) {
        return true;
    }
    if (path.startsWith('/questions/')) {
        return true;
    }
    return false;
};

const shouldHandleSpaLink = (event: MouseEvent, link: HTMLAnchorElement) => {
    if (event.defaultPrevented) {
        return false;
    }
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }
    if (link.target && link.target !== '_self') {
        return false;
    }
    if (link.hasAttribute('download')) {
        return false;
    }
    if (link.dataset.noSpa === '1' || link.closest('[data-no-spa]')) {
        return false;
    }
    const url = new URL(link.href, window.location.origin);
    if (!isSpaEligibleUrl(url)) {
        return false;
    }
    const currentUrl = new URL(window.location.href);
    if (
        url.hash &&
        normalizeSpaPath(url) === normalizeSpaPath(currentUrl) &&
        url.search === currentUrl.search
    ) {
        return false;
    }
    return true;
};

export async function navigateTo(url: string, options: { push?: boolean; scroll?: boolean } = {}) {
    if (spaNavigationPending) {
        return;
    }
    const targetUrl = new URL(url, window.location.origin);
    if (!isSpaEligibleUrl(targetUrl)) {
        window.location.href = targetUrl.toString();
        return;
    }
    if (targetUrl.toString() === window.location.href && options.push !== false) {
        return;
    }
    spaNavigationPending = true;
    try {
        const response = await fetch(targetUrl.toString(), {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            window.location.href = targetUrl.toString();
            return;
        }
        const html = await response.text();
        const nextDoc = new DOMParser().parseFromString(html, 'text/html');
        const nextMain = nextDoc.querySelector<HTMLElement>('main.page');
        const currentMain = document.querySelector<HTMLElement>('main.page');
        if (!nextMain || !currentMain) {
            window.location.href = targetUrl.toString();
            return;
        }
        currentMain.innerHTML = nextMain.innerHTML;
        if (nextDoc.title) {
            document.title = nextDoc.title;
        }
        if (nextDoc.body?.dataset.page) {
            document.body.dataset.page = nextDoc.body.dataset.page;
        }
        if (options.push !== false) {
            window.history.pushState({}, '', targetUrl.toString());
        }
        updateNavActiveState(targetUrl);
        spaDeps?.resetActionMenus();
        spaDeps?.hydratePage();
        if (options.scroll === false) {
            return;
        }
        if (targetUrl.hash) {
            const targetId = targetUrl.hash.replace('#', '');
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'auto', block: 'start' });
                return;
            }
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    } catch {
        window.location.href = targetUrl.toString();
    } finally {
        spaNavigationPending = false;
    }
}

export const setupSpaNavigation = () => {
    if (spaNavigationBound) {
        return;
    }
    spaNavigationBound = true;

    document.addEventListener('click', (event) => {
        const target = event.target as HTMLElement | null;
        const link = target?.closest<HTMLAnchorElement>('a');
        if (!link) {
            return;
        }
        if (!shouldHandleSpaLink(event, link)) {
            return;
        }
        event.preventDefault();
        void navigateTo(link.href);
    });

    window.addEventListener('popstate', () => {
        void navigateTo(window.location.href, { push: false });
    });

    updateNavActiveState(new URL(window.location.href));
};
