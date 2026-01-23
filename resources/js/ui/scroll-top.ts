const BASE_THRESHOLD = 240;

const getHeaderHeight = (): number => {
    const topbar = document.querySelector<HTMLElement>('.topbar');
    if (!topbar) {
        return 0;
    }
    return topbar.getBoundingClientRect().height;
};

export const setupScrollTopRail = () => {
    const rail = document.querySelector<HTMLButtonElement>('[data-scroll-top]');
    if (!rail) {
        return;
    }
    if (rail.dataset.scrollTopBound === '1') {
        return;
    }
    rail.dataset.scrollTopBound = '1';

    const updateVisibility = () => {
        const headerHeight = getHeaderHeight();
        const threshold = Math.max(BASE_THRESHOLD, headerHeight * 2);
        const visible = window.scrollY > threshold;
        rail.dataset.visible = visible ? '1' : '0';
        rail.disabled = !visible;
    };

    const scrollToTop = () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    updateVisibility();
    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility, { passive: true });

    rail.addEventListener('click', (event) => {
        event.preventDefault();
        scrollToTop();
    });

    rail.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        event.preventDefault();
        scrollToTop();
    });
};

