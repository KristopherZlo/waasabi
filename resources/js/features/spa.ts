import { appUrl } from '../core/config';

type SpaDeps = {
    hydratePage: () => void;
    resetActionMenus: () => void;
};

let spaNavigationBound = false;
let spaNavigationController: AbortController | null = null;
let spaNavigationId = 0;
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
    const pages = new Set([
        '/',
        '/read-later',
        '/showcase',
        '/notifications',
        '/publish',
        '/settings',
        '/profile',
        '/login',
        '/register',
        '/forgot-password',
        '/verify-email',
        '/collaboration',
        '/collaboration/create',
    ]);

    return pages.has(path)
        || path.startsWith('/profile/')
        || path.startsWith('/projects/')
        || path.startsWith('/questions/')
        || path.startsWith('/posts/')
        || path.startsWith('/collaboration/')
        || path.startsWith('/reset-password/');
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
    // A document navigation flushes the editor draft and releases its listeners.
    if (document.querySelector('[data-publish-form]')) {
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

type SpaSkeletonKind =
    | 'feed'
    | 'collaboration'
    | 'saved'
    | 'showcase'
    | 'notifications'
    | 'profile'
    | 'detail'
    | 'editor'
    | 'settings'
    | 'auth'
    | 'form';

const skeletonLine = (size = '') => `<span class="spa-skeleton__line${size ? ` spa-skeleton__line--${size}` : ''} skeleton"></span>`;
const skeletonField = '<span class="spa-skeleton__field skeleton"></span>';
const skeletonCard = `
    <div class="spa-skeleton__card">
        <div class="spa-skeleton__identity">
            <span class="spa-skeleton__avatar spa-skeleton__avatar--small skeleton"></span>
            <div class="spa-skeleton__stack">${skeletonLine('medium')}${skeletonLine('short')}</div>
        </div>
        ${skeletonLine()}${skeletonLine('medium')}
    </div>
`;
const skeletonNotification = `
    <div class="spa-skeleton__notification">
        <span class="spa-skeleton__avatar spa-skeleton__avatar--small skeleton"></span>
        <div class="spa-skeleton__stack">${skeletonLine('long')}${skeletonLine('medium')}</div>
    </div>
`;

const skeletonTemplates = {
    feed: `
        <div class="spa-skeleton__layout spa-skeleton__layout--sidebar">
            <section class="spa-skeleton__stack">
                <div class="spa-skeleton__toolbar">${skeletonLine('medium')}<div class="spa-skeleton__tabs">${skeletonField.repeat(3)}</div></div>
                <div class="spa-skeleton__chips">${skeletonField.repeat(4)}</div>
                ${skeletonCard.repeat(3)}
            </section>
            <aside class="spa-skeleton__aside"><div class="spa-skeleton__panel skeleton"></div><div class="spa-skeleton__panel spa-skeleton__panel--short skeleton"></div></aside>
        </div>
    `,
    collaboration: `
        <div class="spa-skeleton__layout spa-skeleton__layout--sidebar">
            <section class="spa-skeleton__stack">
                <div class="spa-skeleton__toolbar">${skeletonLine('medium')}<div class="spa-skeleton__tabs">${skeletonField.repeat(3)}</div></div>
                <div class="spa-skeleton__heading-row"><div class="spa-skeleton__stack">${skeletonLine('title')}${skeletonLine('short')}</div><span class="spa-skeleton__button skeleton"></span></div>
                <div class="spa-skeleton__filters">${skeletonField.repeat(5)}</div>
                ${skeletonCard.repeat(2)}
            </section>
            <aside class="spa-skeleton__aside"><div class="spa-skeleton__panel skeleton"></div><div class="spa-skeleton__panel spa-skeleton__panel--short skeleton"></div></aside>
        </div>
    `,
    saved: `
        <section class="spa-skeleton__stack spa-skeleton__single">
            <div class="spa-skeleton__heading-row"><div class="spa-skeleton__stack">${skeletonLine('title')}${skeletonLine('medium')}</div><span class="spa-skeleton__count skeleton"></span></div>
            ${skeletonCard.repeat(4)}
        </section>
    `,
    showcase: `
        <section class="spa-skeleton__stack spa-skeleton__single">
            <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
            ${skeletonLine('short')}
            <div class="spa-skeleton__gallery">
                <div class="spa-skeleton__gallery-card"><div class="spa-skeleton__cover skeleton"></div>${skeletonLine('medium')}${skeletonLine('short')}</div>
                <div class="spa-skeleton__gallery-card"><div class="spa-skeleton__cover skeleton"></div>${skeletonLine('medium')}${skeletonLine('short')}</div>
            </div>
        </section>
    `,
    notifications: `
        <section class="spa-skeleton__stack spa-skeleton__single">
            <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
            <div class="spa-skeleton__toolbar"><div class="spa-skeleton__tabs">${skeletonField.repeat(2)}</div><span class="spa-skeleton__button skeleton"></span></div>
            ${skeletonNotification.repeat(5)}
        </section>
    `,
    profile: `
        <section class="spa-skeleton__stack spa-skeleton__single">
            <div class="spa-skeleton__profile-cover skeleton"></div>
            <div class="spa-skeleton__profile-head">
                <span class="spa-skeleton__avatar skeleton"></span>
                <div class="spa-skeleton__stack">${skeletonLine('title')}${skeletonLine('medium')}${skeletonLine('short')}</div>
                <span class="spa-skeleton__button skeleton"></span>
            </div>
            <div class="spa-skeleton__tabs">${skeletonField.repeat(3)}</div>
            ${skeletonCard.repeat(2)}
        </section>
    `,
    detail: `
        <div class="spa-skeleton__layout spa-skeleton__layout--sidebar">
            <article class="spa-skeleton__stack">
                <div class="spa-skeleton__identity"><span class="spa-skeleton__avatar spa-skeleton__avatar--small skeleton"></span><div class="spa-skeleton__stack">${skeletonLine('medium')}${skeletonLine('short')}</div></div>
                ${skeletonLine('title')}${skeletonLine('medium')}
                <div class="spa-skeleton__cover skeleton"></div>
                <div class="spa-skeleton__copy">${skeletonLine()}${skeletonLine()}${skeletonLine('medium')}${skeletonLine()}${skeletonLine('short')}</div>
                <div class="spa-skeleton__panel skeleton"></div>
            </article>
            <aside class="spa-skeleton__aside"><div class="spa-skeleton__panel skeleton"></div><div class="spa-skeleton__panel spa-skeleton__panel--short skeleton"></div></aside>
        </div>
    `,
    editor: `
        <section class="spa-skeleton__stack spa-skeleton__single spa-skeleton__single--wide">
            <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
            <div class="spa-skeleton__tabs">${skeletonField.repeat(2)}</div>
            <div class="spa-skeleton__layout spa-skeleton__layout--editor">
                <div class="spa-skeleton__form-panel">${skeletonField.repeat(6)}</div>
                <div class="spa-skeleton__editor skeleton"></div>
            </div>
        </section>
    `,
    settings: `
        <div class="spa-skeleton__layout spa-skeleton__layout--settings spa-skeleton__single--wide">
            <aside class="spa-skeleton__settings-nav"><div class="spa-skeleton__identity"><span class="spa-skeleton__avatar spa-skeleton__avatar--small skeleton"></span>${skeletonLine('medium')}</div>${skeletonField}${skeletonField}${skeletonField}${skeletonField}</aside>
            <section class="spa-skeleton__stack">
                <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
                <div class="spa-skeleton__settings-row">${skeletonLine('medium')}${skeletonField}</div>
                <div class="spa-skeleton__settings-row">${skeletonLine('medium')}${skeletonField}</div>
                <div class="spa-skeleton__settings-row">${skeletonLine('medium')}${skeletonField}</div>
                <div class="spa-skeleton__settings-row">${skeletonLine('medium')}${skeletonField}</div>
            </section>
        </div>
    `,
    auth: `
        <section class="spa-skeleton__stack spa-skeleton__auth">
            <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
            <div class="spa-skeleton__form-panel">${skeletonField.repeat(4)}<span class="spa-skeleton__button spa-skeleton__button--wide skeleton"></span></div>
        </section>
    `,
    form: `
        <section class="spa-skeleton__stack spa-skeleton__form">
            <div class="spa-skeleton__hero">${skeletonLine('title')}${skeletonLine('medium')}</div>
            <div class="spa-skeleton__form-panel">${skeletonField.repeat(5)}<div class="spa-skeleton__editor spa-skeleton__editor--short skeleton"></div><span class="spa-skeleton__button skeleton"></span></div>
        </section>
    `,
} satisfies Record<SpaSkeletonKind, string>;

const getSpaSkeletonKind = (url: URL): SpaSkeletonKind => {
    const path = normalizeSpaPath(url);

    if (path === '/' && url.searchParams.get('stream') === 'collaboration') return 'collaboration';
    if (path === '/' ) return 'feed';
    if (path === '/collaboration') return 'collaboration';
    if (path === '/read-later') return 'saved';
    if (path === '/showcase') return 'showcase';
    if (path === '/notifications') return 'notifications';
    if (path === '/settings' || path === '/profile/settings') return 'settings';
    if (path === '/profile' || path.startsWith('/profile/')) return 'profile';
    if (path === '/publish' || (path.startsWith('/posts/') && path.endsWith('/edit'))) return 'editor';
    if (path === '/collaboration/create') return 'form';
    if (['/login', '/register', '/forgot-password', '/verify-email'].includes(path) || path.startsWith('/reset-password/')) return 'auth';
    if (path.startsWith('/projects/') || path.startsWith('/questions/') || path.startsWith('/posts/') || path.startsWith('/collaboration/')) return 'detail';

    return 'feed';
};

const showSpaLoadingState = (targetUrl: URL) => {
    const main = document.querySelector<HTMLElement>('main.page');
    if (!main) {
        return;
    }

    const kind = getSpaSkeletonKind(targetUrl);
    const placeholder = document.createElement('div');
    placeholder.className = `spa-placeholder spa-placeholder--${kind}`;
    placeholder.dataset.skeleton = kind;
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.innerHTML = skeletonTemplates[kind];

    main.setAttribute('aria-busy', 'true');
    main.replaceChildren(placeholder);
};

export async function navigateTo(url: string, options: { push?: boolean; scroll?: boolean } = {}) {
    const targetUrl = new URL(url, window.location.origin);
    if (!isSpaEligibleUrl(targetUrl)) {
        window.location.href = targetUrl.toString();
        return;
    }
    if (targetUrl.toString() === window.location.href && options.push !== false) {
        return;
    }
    spaNavigationController?.abort();
    const controller = new AbortController();
    const navigationId = ++spaNavigationId;
    spaNavigationController = controller;
    document.body.dataset.spaLoading = '1';
    if (options.push !== false) {
        window.history.pushState({}, '', targetUrl.toString());
    }
    updateNavActiveState(targetUrl);
    showSpaLoadingState(targetUrl);
    if (options.scroll !== false) {
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    }

    try {
        const response = await fetch(targetUrl.toString(), {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPA-Fragment': '1',
            },
            signal: controller.signal,
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
        const responseUrl = new URL(response.url || targetUrl.toString());
        if (!isSpaEligibleUrl(responseUrl)) {
            window.location.href = responseUrl.toString();
            return;
        }

        currentMain.replaceWith(nextMain);
        document.title = nextMain.dataset.title || nextDoc.title || document.title;
        document.body.dataset.page = nextMain.dataset.page ?? 'feed';
        if (nextMain.dataset.toastMessage) {
            document.body.dataset.toastMessage = nextMain.dataset.toastMessage;
        } else {
            delete document.body.dataset.toastMessage;
        }
        const canonical = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
        if (canonical) {
            canonical.href = nextMain.dataset.canonical || responseUrl.toString();
        }
        if (responseUrl.toString() !== window.location.href) {
            window.history.replaceState({}, '', responseUrl.toString());
        }
        updateNavActiveState(responseUrl);
        spaDeps?.resetActionMenus();
        spaDeps?.hydratePage();
        if (options.scroll === false) {
            return;
        }
        if (responseUrl.hash) {
            const targetId = responseUrl.hash.replace('#', '');
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'auto', block: 'start' });
                return;
            }
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError') && navigationId === spaNavigationId) {
            window.location.href = targetUrl.toString();
        }
    } finally {
        if (navigationId === spaNavigationId) {
            spaNavigationController = null;
            delete document.body.dataset.spaLoading;
            document.querySelector<HTMLElement>('main.page')?.removeAttribute('aria-busy');
        }
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
