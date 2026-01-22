import { tFormat } from '../core/i18n';
import { setupIcons } from '../core/media';

type ViewerImage = {
    src: string;
    alt: string;
};

type ViewerRefs = {
    viewer: HTMLElement;
    image: HTMLImageElement;
    thumbs: HTMLElement;
    prev: HTMLButtonElement | null;
    next: HTMLButtonElement | null;
    closes: HTMLButtonElement[];
};

let refs: ViewerRefs | null = null;
let images: ViewerImage[] = [];
let currentIndex = 0;
let keydownBound = false;

const clampIndex = (index: number) => Math.max(0, Math.min(index, images.length - 1));

const isViewerOpen = () => refs !== null && !refs.viewer.hidden;

const closeViewer = () => {
    if (!refs) {
        return;
    }
    refs.viewer.hidden = true;
    document.body.classList.remove('is-locked');
    images = [];
    currentIndex = 0;
};

const setActiveThumb = (index: number) => {
    if (!refs) {
        return;
    }
    const thumbButtons = Array.from(refs.thumbs.querySelectorAll<HTMLButtonElement>('.media-viewer__thumb'));
    thumbButtons.forEach((button, thumbIndex) => {
        button.classList.toggle('is-active', thumbIndex === index);
    });
    const active = thumbButtons[index];
    active?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
};

const rebuildThumbs = () => {
    if (!refs) {
        return;
    }
    refs.thumbs.innerHTML = '';
    images.forEach((image, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'media-viewer__thumb';
        button.setAttribute(
            'aria-label',
            tFormat('media_viewer_thumb', 'Open image :index', { index: index + 1 }),
        );
        button.addEventListener('click', () => {
            currentIndex = index;
            renderViewer();
        });

        const thumb = document.createElement('img');
        thumb.src = image.src;
        thumb.alt = image.alt;
        button.appendChild(thumb);
        refs?.thumbs.appendChild(button);
    });
};

const renderViewer = () => {
    if (!refs || images.length === 0) {
        return;
    }
    const safeIndex = clampIndex(currentIndex);
    currentIndex = safeIndex;
    const activeImage = images[safeIndex];
    refs.image.src = activeImage.src;
    refs.image.alt = activeImage.alt;

    if (refs.prev) {
        refs.prev.hidden = images.length <= 1;
        refs.prev.disabled = safeIndex <= 0;
    }
    if (refs.next) {
        refs.next.hidden = images.length <= 1;
        refs.next.disabled = safeIndex >= images.length - 1;
    }
    refs.thumbs.hidden = images.length <= 1;
    setActiveThumb(safeIndex);
};

const openViewer = (nextImages: ViewerImage[], startIndex: number) => {
    if (!refs || !nextImages.length) {
        return;
    }
    images = nextImages;
    currentIndex = clampIndex(startIndex);
    rebuildThumbs();
    refs.viewer.hidden = false;
    document.body.classList.add('is-locked');
    renderViewer();
};

const handleKeydown = (event: KeyboardEvent) => {
    if (!refs || refs.viewer.hidden) {
        return;
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        closeViewer();
        return;
    }
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        currentIndex = clampIndex(currentIndex + 1);
        renderViewer();
        return;
    }
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        currentIndex = clampIndex(currentIndex - 1);
        renderViewer();
    }
};

const isBlockedByNsfw = (img: HTMLImageElement) => {
    const cover = img.closest<HTMLElement>('[data-nsfw-cover]');
    if (!cover) {
        return false;
    }
    return cover.classList.contains('is-nsfw') && !cover.classList.contains('is-revealed');
};

const collectImages = (img: HTMLImageElement) => {
    const carousel = img.closest<HTMLElement>('[data-carousel]');
    const cover = img.closest<HTMLElement>('.post-cover');
    const scope = carousel ?? cover;
    const nodes = scope
        ? Array.from(scope.querySelectorAll<HTMLImageElement>('img'))
        : [img];
    const list = nodes
        .map((node) => ({
            src: node.currentSrc || node.src,
            alt: node.alt || img.alt || '',
        }))
        .filter((node) => node.src.trim() !== '');
    const startIndex = Math.max(0, nodes.indexOf(img));
    return { list, startIndex };
};

const bindImage = (img: HTMLImageElement) => {
    if (img.dataset.mediaViewerBound === '1') {
        return;
    }
    img.dataset.mediaViewerBound = '1';
    img.addEventListener('click', (event) => {
        if (isBlockedByNsfw(img)) {
            return;
        }
        event.preventDefault();
        const { list, startIndex } = collectImages(img);
        openViewer(list, startIndex);
    });
};

const scanImages = (root: ParentNode) => {
    const candidates = root.querySelectorAll<HTMLImageElement>(
        '.post-cover img, [data-carousel] .post-carousel__slide img',
    );
    candidates.forEach(bindImage);
};

const initViewer = () => {
    const viewer = document.querySelector<HTMLElement>('[data-media-viewer]');
    if (!viewer) {
        return;
    }
    if (refs) {
        return;
    }
    const image = viewer.querySelector<HTMLImageElement>('[data-media-viewer-image]');
    const thumbs = viewer.querySelector<HTMLElement>('[data-media-viewer-thumbs]');
    if (!image || !thumbs) {
        return;
    }
    const prev = viewer.querySelector<HTMLButtonElement>('[data-media-viewer-prev]');
    const next = viewer.querySelector<HTMLButtonElement>('[data-media-viewer-next]');
    const closes = Array.from(viewer.querySelectorAll<HTMLButtonElement>('[data-media-viewer-close]'));

    refs = { viewer, image, thumbs, prev, next, closes };

    prev?.addEventListener('click', () => {
        currentIndex = clampIndex(currentIndex - 1);
        renderViewer();
    });
    next?.addEventListener('click', () => {
        currentIndex = clampIndex(currentIndex + 1);
        renderViewer();
    });
    closes.forEach((button) => button.addEventListener('click', closeViewer));

    if (!keydownBound) {
        document.addEventListener('keydown', handleKeydown);
        keydownBound = true;
    }

    setupIcons(viewer);
};

export const setupMediaViewer = (root: ParentNode = document) => {
    initViewer();
    if (!refs) {
        return;
    }
    scanImages(root);
    if (root !== document && isViewerOpen() && images.length === 0) {
        closeViewer();
    }
};
