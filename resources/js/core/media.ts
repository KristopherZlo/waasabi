import { createIcons, icons } from 'lucide';
import { createAvatarFromName } from '../../../scribble-generator/scribble-avatar';
import type { DomRoot } from './types';

export const setupIcons = (root: DomRoot = document) => {
    createIcons({ icons, root });
};

export const setupImageFallbacks = (root: DomRoot = document) => {
    const fallbackDefault = document.body.dataset.placeholder ?? '';
    const images = root.querySelectorAll<HTMLImageElement>('img');
    if (!images.length) {
        return;
    }
    images.forEach((image) => {
        const fallback = image.dataset.fallback ?? fallbackDefault;
        if (!fallback) {
            return;
        }
        if (image.dataset.fallbackBound === '1') {
            return;
        }
        image.dataset.fallbackBound = '1';
        const applyFallback = () => {
            if (image.dataset.fallbackApplied) {
                return;
            }
            image.dataset.fallbackApplied = 'true';
            image.src = fallback;
        };
        image.addEventListener('error', applyFallback);
        if (!image.src) {
            applyFallback();
        }
    });
};

export const setupScribbleAvatars = (root: DomRoot = document) => {
    const avatars = Array.from(root.querySelectorAll<HTMLImageElement>('img.avatar'));
    if (!avatars.length) {
        return;
    }
    avatars.forEach((avatar) => {
        const name = avatar.dataset.avatarName ?? avatar.getAttribute('alt') ?? '';
        applyScribbleAvatar(avatar, name);
    });
};

export const applyScribbleAvatar = (avatar: HTMLImageElement, name: string) => {
    if (avatar.dataset.avatarGenerated === '1') {
        return;
    }
    const shouldAuto =
        avatar.dataset.avatarAuto === '1' || (avatar.getAttribute('src') ?? '').includes('avatar-default.svg');
    if (!shouldAuto) {
        return;
    }
    if (!name.trim()) {
        return;
    }
    try {
        const result = createAvatarFromName(name);
        avatar.src = result.dataUrl;
        avatar.dataset.avatarGenerated = '1';
    } catch {
        // ignore
    }
};
