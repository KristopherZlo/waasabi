let navFooterOffsetBound = false;
let navFooterOffsetUpdate: (() => void) | null = null;

export const setupNavFooterOffset = () => {
    const nav = document.querySelector<HTMLElement>('.nav');
    const footer = document.querySelector<HTMLElement>('.site-footer');
    const topbar = document.querySelector<HTMLElement>('.topbar');
    if (!nav || !footer) {
        return;
    }
    if (navFooterOffsetBound) {
        navFooterOffsetUpdate?.();
        return;
    }

    const baseOffset = 12;
    const safeGap = 12;
    let ticking = false;

    const update = () => {
        const footerRect = footer.getBoundingClientRect();
        const requiredOffset = window.innerHeight - footerRect.top + safeGap;
        const nextBottom = Math.max(baseOffset, requiredOffset);
        nav.style.bottom = `${nextBottom}px`;
        if (topbar) {
            const topbarRect = topbar.getBoundingClientRect();
            const height = Math.max(0, Math.round(topbarRect.height));
            document.documentElement.style.setProperty('--topbar-height', `${height}px`);
        }
    };

    const scheduleUpdate = () => {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(() => {
            update();
            ticking = false;
        });
    };

    update();
    navFooterOffsetBound = true;
    navFooterOffsetUpdate = scheduleUpdate;
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    window.addEventListener('load', scheduleUpdate);

    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(() => scheduleUpdate());
        observer.observe(footer);
        observer.observe(document.documentElement);
        observer.observe(document.body);
    } else if ('MutationObserver' in window) {
        const observer = new MutationObserver(() => scheduleUpdate());
        observer.observe(document.body, { childList: true, subtree: true });
    }
};
