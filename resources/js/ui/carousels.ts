import { t } from '../core/i18n';
import { setupIcons } from '../core/media';

export const buildCarouselElement = (images: HTMLImageElement[], variant: 'inline' | 'cover' = 'inline') => {
    const carousel = document.createElement('div');
    carousel.className = `post-carousel ${variant === 'inline' ? 'post-carousel--inline' : 'post-carousel--cover'}`;
    carousel.dataset.carousel = '1';
    carousel.setAttribute('aria-label', t('carousel_label', 'Image carousel'));

    const track = document.createElement('div');
    track.className = 'post-carousel__track';
    track.dataset.carouselTrack = '1';

    images.forEach((image) => {
        image.classList.add('post-carousel__image');
        const slide = document.createElement('div');
        slide.className = 'post-carousel__slide';
        if (image.src) {
            slide.style.setProperty('--carousel-bg', `url("${image.src}")`);
        }
        slide.appendChild(image);
        track.appendChild(slide);
    });

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.className = 'icon-btn post-carousel__control post-carousel__control--prev';
    prevButton.dataset.carouselPrev = '1';
    prevButton.setAttribute('aria-label', t('carousel_prev', 'Previous image'));
    prevButton.innerHTML = '<i data-lucide="chevron-left" class="icon"></i>';

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'icon-btn post-carousel__control post-carousel__control--next';
    nextButton.dataset.carouselNext = '1';
    nextButton.setAttribute('aria-label', t('carousel_next', 'Next image'));
    nextButton.innerHTML = '<i data-lucide="chevron-right" class="icon"></i>';

    const dots = document.createElement('div');
    dots.className = 'post-carousel__dots';
    dots.dataset.carouselDots = '1';

    carousel.append(prevButton, track, nextButton, dots);
    setupIcons(carousel);
    return carousel;
};

export const setupCarousels = (root: ParentNode = document) => {
    const carousels = [
        ...(root instanceof HTMLElement && root.matches('[data-carousel]') ? [root] : []),
        ...Array.from(root.querySelectorAll<HTMLElement>('[data-carousel]')),
    ];
    if (!carousels.length) {
        return;
    }

    const measureCarouselHeight = (carousel: HTMLElement, slides: HTMLElement[]) => {
        let maxHeight = 0;
        slides.forEach((slide) => {
            const img = slide.querySelector<HTMLImageElement>('img');
            if (!img) {
                return;
            }
            const rect = img.getBoundingClientRect();
            if (rect.height > maxHeight) {
                maxHeight = rect.height;
            }
        });
        if (maxHeight > 0) {
            carousel.style.setProperty('--carousel-height', `${Math.ceil(maxHeight)}px`);
        }
    };

    carousels.forEach((carousel) => {
        if (carousel.dataset.carouselBound === '1') {
            return;
        }
        carousel.dataset.carouselBound = '1';
        const track = carousel.querySelector<HTMLElement>('[data-carousel-track]');
        if (!track) {
            return;
        }
        const slides = Array.from(track.querySelectorAll<HTMLElement>('.post-carousel__slide'));
        slides.forEach((slide) => {
            const current = slide.style.getPropertyValue('--carousel-bg');
            if (current.trim() !== '') {
                return;
            }
            const img = slide.querySelector<HTMLImageElement>('img');
            if (img?.src) {
                slide.style.setProperty('--carousel-bg', `url("${img.src}")`);
            }
        });
        const prevButton = carousel.querySelector<HTMLButtonElement>('[data-carousel-prev]');
        const nextButton = carousel.querySelector<HTMLButtonElement>('[data-carousel-next]');
        let dots = carousel.querySelector<HTMLElement>('[data-carousel-dots]');

        if (slides.length <= 1) {
            prevButton?.setAttribute('hidden', 'true');
            nextButton?.setAttribute('hidden', 'true');
            dots?.setAttribute('hidden', 'true');
            return;
        }

        if (!dots) {
            dots = document.createElement('div');
            dots.className = 'post-carousel__dots';
            dots.dataset.carouselDots = '1';
            carousel.appendChild(dots);
        }

        dots.innerHTML = '';
        slides.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'post-carousel__dot';
            dot.dataset.carouselDot = String(index);
            dot.setAttribute('aria-label', t('carousel_dot', 'Go to slide'));
            dot.addEventListener('click', () => {
                const width = track.clientWidth || 1;
                track.scrollTo({ left: width * index, behavior: 'smooth' });
            });
            dots?.appendChild(dot);
        });

        const clampIndex = (index: number) => Math.max(0, Math.min(index, slides.length - 1));
        const getIndex = () => {
            const width = track.clientWidth || 1;
            return clampIndex(Math.round(track.scrollLeft / width));
        };

        const setActive = (index: number) => {
            const activeIndex = clampIndex(index);
            const dotButtons = dots?.querySelectorAll<HTMLButtonElement>('.post-carousel__dot') ?? [];
            dotButtons.forEach((button, buttonIndex) => {
                button.classList.toggle('is-active', buttonIndex === activeIndex);
            });
            if (prevButton) {
                prevButton.disabled = activeIndex <= 0;
            }
            if (nextButton) {
                nextButton.disabled = activeIndex >= slides.length - 1;
            }
        };

        const scrollToIndex = (index: number) => {
            const width = track.clientWidth || 1;
            track.scrollTo({ left: width * clampIndex(index), behavior: 'smooth' });
        };

        prevButton?.addEventListener('click', () => {
            scrollToIndex(getIndex() - 1);
        });
        nextButton?.addEventListener('click', () => {
            scrollToIndex(getIndex() + 1);
        });

        let ticking = false;
        track.addEventListener('scroll', () => {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(() => {
                setActive(getIndex());
                ticking = false;
            });
        });

        const updateHeight = () => measureCarouselHeight(carousel, slides);
        const scheduleHeight = () => window.requestAnimationFrame(updateHeight);

        slides.forEach((slide) => {
            const img = slide.querySelector<HTMLImageElement>('img');
            if (!img) {
                return;
            }
            if (img.complete) {
                scheduleHeight();
            } else {
                img.addEventListener('load', scheduleHeight, { once: true });
                img.addEventListener('error', scheduleHeight, { once: true });
            }
        });

        window.addEventListener('resize', () => {
            setActive(getIndex());
            scheduleHeight();
        });
        setActive(getIndex());
        scheduleHeight();
    });
};

export const buildInlineCarousels = (article: HTMLElement) => {
    const blocks = Array.from(article.children);
    if (!blocks.length) {
        return;
    }

    let pendingImages: HTMLImageElement[] = [];
    let pendingNodes: HTMLElement[] = [];

    const extractImage = (node: Element) => {
        if (node.tagName === 'P') {
            const images = node.querySelectorAll<HTMLImageElement>('img');
            const text = node.textContent?.trim() ?? '';
            if (images.length === 1 && text === '') {
                return images[0];
            }
        }
        if (node.tagName === 'IMG') {
            return node as HTMLImageElement;
        }
        return null;
    };

    const flush = () => {
        if (pendingImages.length > 1 && pendingNodes.length) {
            const carousel = buildCarouselElement(pendingImages, 'inline');
            pendingNodes[0].before(carousel);
            pendingNodes.forEach((node) => node.remove());
            setupCarousels(carousel);
        }
        pendingImages = [];
        pendingNodes = [];
    };

    blocks.forEach((node) => {
        const image = extractImage(node);
        if (image) {
            pendingImages.push(image);
            pendingNodes.push(node as HTMLElement);
            return;
        }
        flush();
    });

    flush();
};
