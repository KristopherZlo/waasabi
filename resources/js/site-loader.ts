import { createAvatarFromSeed } from '../../scribble-generator/scribble-avatar';

const setupSiteLoader = () => {
    const loader = document.querySelector<HTMLElement>('[data-site-loader]');
    const avatar = loader?.querySelector<HTMLElement>('[data-site-loader-avatar]') ?? null;
    if (!loader || !avatar) {
        return;
    }

    const updateAvatar = () => {
        const seed = Math.floor(Math.random() * 4294967296);
        const result = createAvatarFromSeed(seed);
        const svg = result.svg.replace(/<\?xml[^>]*\?>/i, '').replace(/<rect[^>]*\/>/, '');
        avatar.innerHTML = svg;
    };

    updateAvatar();
    const interval = window.setInterval(updateAvatar, 250);

    const finish = () => {
        if (loader.classList.contains('is-hiding')) {
            return;
        }
        loader.classList.add('is-hiding');
        window.clearInterval(interval);
        window.setTimeout(() => {
            loader.hidden = true;
        }, 1100);
    };

    if (document.readyState === 'complete') {
        finish();
    } else {
        window.addEventListener('load', finish);
    }
};

setupSiteLoader();
