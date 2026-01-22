import { createAvatarFromName, seedFromName } from '../../../scribble-generator/scribble-avatar';

const cleanSvg = (svg: string) =>
    svg.replace(/<\?xml[^>]*\?>/i, '').replace(/<rect[^>]*\/>/i, '').trim();

const createSeededRng = (seed: number) => {
    let t = seed >>> 0;
    return () => {
        t += 0x6d2b79f5;
        let x = t;
        x = Math.imul(x ^ (x >>> 15), x | 1);
        x ^= x + Math.imul(x ^ (x >>> 7), x | 61);
        return ((x ^ (x >>> 14)) >>> 0) / 4294967296;
    };
};

const randomBetween = (rng: () => number, min: number, max: number) => min + rng() * (max - min);

const buildSparks = (container: HTMLElement, name: string) => {
    container.innerHTML = '';
    const rng = createSeededRng(seedFromName(name || 'guest'));
    const positions = [
        { x: 88, y: 16 },
        { x: 12, y: 84 },
    ];
    positions.forEach(({ x, y }) => {
        const spark = document.createElement('span');
        spark.className = 'profile-banner__spark';
        const hue = Math.round(randomBetween(rng, 0, 360));
        const sat = Math.round(randomBetween(rng, 70, 95));
        const light = Math.round(randomBetween(rng, 55, 75));
        const alpha = randomBetween(rng, 0.35, 0.7).toFixed(2);
        const size = Math.round(randomBetween(rng, 180, 320));
        const blur = Math.round(randomBetween(rng, 12, 20));
        const opacity = randomBetween(rng, 0.45, 0.85).toFixed(2);
        const rotate = Math.round(randomBetween(rng, -16, 16));

        spark.style.setProperty('--spark-x', `${x}%`);
        spark.style.setProperty('--spark-y', `${y}%`);
        spark.style.setProperty('--spark-size', `${size}px`);
        spark.style.setProperty('--spark-blur', `${blur}px`);
        spark.style.setProperty('--spark-opacity', opacity);
        spark.style.setProperty('--spark-rotate', `${rotate}deg`);
        spark.style.setProperty('--spark-color', `hsla(${hue}, ${sat}%, ${light}%, ${alpha})`);

        container.appendChild(spark);
    });
};

const resolveProfileName = (banner: HTMLElement) => {
    const direct = banner.dataset.profileName ?? '';
    if (direct.trim()) {
        return direct.trim();
    }
    const avatar = document.querySelector<HTMLImageElement>('.profile-header .avatar');
    const fromAlt = avatar?.getAttribute('alt') ?? '';
    return fromAlt.trim();
};

export const setupProfileBanner = () => {
    const banners = Array.from(document.querySelectorAll<HTMLElement>('[data-profile-banner]'));
    if (!banners.length) {
        return;
    }

    banners.forEach((banner) => {
        if (banner.dataset.profileBannerBound === '1') {
            return;
        }
        banner.dataset.profileBannerBound = '1';

        const glow = banner.querySelector<HTMLElement>('[data-profile-banner-glow]');
        const scribble = banner.querySelector<HTMLElement>('[data-profile-banner-scribble]');
        const name = resolveProfileName(banner);
        const bannerImage = (banner.dataset.profileBannerImage ?? '').trim();
        if (bannerImage) {
            banner.classList.add('has-custom-banner');
            banner.style.setProperty('--profile-banner-image', `url("${bannerImage}")`);
            if (glow) {
                glow.innerHTML = '';
            }
            if (scribble) {
                scribble.innerHTML = '';
            }
            return;
        }
        if (glow) {
            buildSparks(glow, name);
        }
        if (scribble && name) {
            try {
                const result = createAvatarFromName(name, { svgPointBudget: 1600 });
                scribble.innerHTML = cleanSvg(result.svg);
                const svg = scribble.querySelector('svg');
                if (svg) {
                    svg.setAttribute('aria-hidden', 'true');
                    svg.setAttribute('focusable', 'false');
                }
            } catch {
                // ignore
            }
        }
    });
};
