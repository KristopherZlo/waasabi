const CONSENT_KEY = 'waasabi.cookie.consent.v1';

type ConsentValue = 'accepted' | 'essential';

const readConsent = (): ConsentValue | null => {
    try {
        const value = window.localStorage.getItem(CONSENT_KEY);
        return value === 'accepted' || value === 'essential' ? value : null;
    } catch (error) {
        return null;
    }
};

const writeConsent = (value: ConsentValue) => {
    try {
        window.localStorage.setItem(CONSENT_KEY, value);
    } catch (error) {
        // Ignore storage failures and still hide the banner for this session.
    }
};

export const setupCookieConsent = () => {
    const banner = document.querySelector<HTMLElement>('[data-cookie-banner]');
    if (!banner || banner.dataset.cookieBound === '1') {
        return;
    }
    banner.dataset.cookieBound = '1';

    const acceptButton = banner.querySelector<HTMLButtonElement>('[data-cookie-accept]');
    const essentialButton = banner.querySelector<HTMLButtonElement>('[data-cookie-essential]');
    const body = document.body;
    let resizeObserver: ResizeObserver | null = null;

    const updateOffset = () => {
        if (!body.classList.contains('has-cookie-banner')) {
            body.style.removeProperty('--cookie-banner-offset');
            return;
        }
        const height = banner.getBoundingClientRect().height;
        const offset = Math.ceil(height + 24);
        body.style.setProperty('--cookie-banner-offset', `${offset}px`);
    };

    const hideBanner = () => {
        banner.dataset.visible = 'false';
        banner.setAttribute('hidden', 'hidden');
        body.classList.remove('has-cookie-banner');
        body.style.removeProperty('--cookie-banner-offset');
        resizeObserver?.disconnect();
        resizeObserver = null;
        window.removeEventListener('resize', updateOffset);
    };

    const showBanner = () => {
        banner.removeAttribute('hidden');
        banner.dataset.visible = 'true';
        body.classList.add('has-cookie-banner');
        updateOffset();
        window.addEventListener('resize', updateOffset, { passive: true });
        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(updateOffset);
            resizeObserver.observe(banner);
        }
    };

    if (readConsent()) {
        hideBanner();
        return;
    }

    acceptButton?.addEventListener('click', () => {
        writeConsent('accepted');
        hideBanner();
    });

    essentialButton?.addEventListener('click', () => {
        writeConsent('essential');
        hideBanner();
    });

    showBanner();
};

