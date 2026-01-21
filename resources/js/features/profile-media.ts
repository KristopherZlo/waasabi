import { appUrl, csrfToken } from '../core/config';
import { applyScribbleAvatar } from '../core/media';
import { t } from '../core/i18n';
import { toast } from '../core/toast';
import { setupProfileBanner } from '../ui/profile-banner';

type ProfileMediaAction = 'banner' | 'avatar';

type ProfileMediaConfig = {
    aspect: number;
    minWidth: number;
    minHeight: number;
    targetWidth: number;
    targetHeight: number;
    maxBytes: number;
    titleKey: string;
};

const MB = 1024 * 1024;
const MAX_ZOOM = 3;

const mediaConfigs: Record<ProfileMediaAction, ProfileMediaConfig> = {
    banner: {
        aspect: 4,
        minWidth: 1200,
        minHeight: 300,
        targetWidth: 1600,
        targetHeight: 400,
        maxBytes: 5 * MB,
        titleKey: 'profile_media_banner_title',
    },
    avatar: {
        aspect: 1,
        minWidth: 256,
        minHeight: 256,
        targetWidth: 512,
        targetHeight: 512,
        maxBytes: 2 * MB,
        titleKey: 'profile_media_avatar_title',
    },
};

const toFormUrl = (base: string, slug: string, action: ProfileMediaAction) =>
    `${base}/profile/${encodeURIComponent(slug)}/${action}`;

const readErrorMessage = async (response: Response) => {
    try {
        const data = (await response.json()) as { message?: string };
        return data.message ?? '';
    } catch {
        return '';
    }
};

const clamp = (value: number, min: number, max: number) => {
    if (min > max) {
        return value;
    }
    return Math.min(max, Math.max(min, value));
};

const formatBytes = (bytes: number) => {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 MB';
    }
    const mb = bytes / MB;
    if (mb >= 1) {
        return `${mb.toFixed(mb >= 10 ? 0 : 1)} MB`;
    }
    const kb = bytes / 1024;
    return `${Math.max(1, Math.round(kb))} KB`;
};

const withPlaceholders = (template: string, values: Record<string, string | number>) =>
    template.replace(/:([a-z_]+)/gi, (match, key: string) =>
        key in values ? String(values[key]) : match,
    );

const loadImage = (url: string) =>
    new Promise<HTMLImageElement>((resolve, reject) => {
        const image = new Image();
        image.decoding = 'async';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image_load_failed'));
        image.src = url;
    });

const fileWithExtension = (file: File, extension: string, type: string, blob: Blob) => {
    const baseName = file.name.replace(/\.[^.]+$/, '') || 'image';
    const name = `${baseName}.${extension}`;
    return new File([blob], name, { type });
};

export const setupProfileMedia = () => {
    const banner = document.querySelector<HTMLElement>('[data-profile-banner]');
    const bannerInput = document.querySelector<HTMLInputElement>('[data-profile-banner-input]');
    const bannerTrigger = document.querySelector<HTMLElement>('[data-profile-banner-change]');
    const avatarInput = document.querySelector<HTMLInputElement>('[data-profile-avatar-input]');
    const avatarTrigger = document.querySelector<HTMLElement>('[data-profile-avatar-trigger]');

    if (!banner || (!bannerInput && !avatarInput)) {
        return;
    }

    const slug = (banner.dataset.profileUserSlug ?? '').trim();
    const profileName = (banner.dataset.profileName ?? '').trim();
    if (!slug || !appUrl) {
        return;
    }

    const modal = document.querySelector<HTMLElement>('[data-profile-media-modal]');
    const panel = modal?.querySelector<HTMLElement>('[data-profile-media-panel]') ?? null;
    const titleEl = modal?.querySelector<HTMLElement>('[data-profile-media-title]') ?? null;
    const infoEl = modal?.querySelector<HTMLElement>('[data-profile-media-info]') ?? null;
    const editorEl = modal?.querySelector<HTMLElement>('[data-profile-media-editor]') ?? null;
    const frameEl = modal?.querySelector<HTMLElement>('[data-profile-media-frame]') ?? null;
    const imageEl = modal?.querySelector<HTMLImageElement>('[data-profile-media-image]') ?? null;
    const zoomInput = modal?.querySelector<HTMLInputElement>('[data-profile-media-zoom]') ?? null;
    const zoomValueEl = modal?.querySelector<HTMLElement>('[data-profile-media-zoom-value]') ?? null;
    const errorEl = modal?.querySelector<HTMLElement>('[data-profile-media-error]') ?? null;
    const removeBtn = modal?.querySelector<HTMLButtonElement>('[data-profile-media-remove]') ?? null;
    const chooseBtn = modal?.querySelector<HTMLButtonElement>('[data-profile-media-choose]') ?? null;
    const cancelBtn = modal?.querySelector<HTMLButtonElement>('[data-profile-media-cancel]') ?? null;
    const applyBtn = modal?.querySelector<HTMLButtonElement>('[data-profile-media-apply]') ?? null;
    const closeBtn = modal?.querySelector<HTMLButtonElement>('[data-profile-media-close]') ?? null;
    const loadingEl = modal?.querySelector<HTMLElement>('[data-profile-media-loading]') ?? null;
    const loadingTextEl = loadingEl?.querySelector<HTMLElement>('.profile-media-loading__text') ?? null;

    const modalReady =
        modal &&
        panel &&
        titleEl &&
        infoEl &&
        editorEl &&
        frameEl &&
        imageEl &&
        zoomInput &&
        zoomValueEl &&
        errorEl &&
        removeBtn &&
        chooseBtn &&
        cancelBtn &&
        applyBtn &&
        closeBtn &&
        loadingEl;

    const updateBanner = (url: string) => {
        banner.dataset.profileBannerImage = url;
        banner.classList.add('has-custom-banner');
        banner.style.setProperty('--profile-banner-image', `url("${url}")`);
        const glow = banner.querySelector<HTMLElement>('[data-profile-banner-glow]');
        const scribble = banner.querySelector<HTMLElement>('[data-profile-banner-scribble]');
        if (glow) {
            glow.innerHTML = '';
        }
        if (scribble) {
            scribble.innerHTML = '';
        }
    };

    const updateAvatar = (url: string) => {
        const selectors = [
            '[data-profile-avatar-image]',
            '.user-menu__trigger img.avatar',
            '.user-menu__identity img.avatar',
        ];
        const avatars = Array.from(document.querySelectorAll<HTMLImageElement>(selectors.join(', ')));
        avatars.forEach((img) => {
            if (profileName && img.alt && img.alt.trim() !== profileName) {
                return;
            }
            img.src = url;
        });
        if (profileName) {
            const byAlt = Array.from(document.querySelectorAll<HTMLImageElement>('img.avatar'));
            byAlt.forEach((img) => {
                if ((img.alt ?? '').trim() === profileName) {
                    img.src = url;
                }
            });
        }
    };

    const upload = async (file: File, action: ProfileMediaAction) => {
        const url = toFormUrl(appUrl, slug, action);
        const form = new FormData();
        form.append(action === 'banner' ? 'banner_file' : 'avatar_file', file);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: form,
        });
        if (!response.ok) {
            const message = await readErrorMessage(response);
            throw new Error(message || response.statusText);
        }
        const data = (await response.json()) as { url?: string };
        if (!data.url) {
            throw new Error('missing_url');
        }
        return data.url;
    };

    const bindInput = (
        input: HTMLInputElement | null,
        action: ProfileMediaAction,
        onSuccess: (url: string) => void,
        successKey: string,
        failureKey: string,
    ) => {
        if (!input || input.dataset.profileMediaBound === '1') {
            return;
        }
        input.dataset.profileMediaBound = '1';
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }
            try {
                const uploadedUrl = await upload(file, action);
                onSuccess(uploadedUrl);
                toast.show(t(successKey, action === 'banner' ? 'Banner updated.' : 'Avatar updated.'));
            } catch (error) {
                const message = error instanceof Error ? error.message : '';
                toast.show(
                    message ||
                        t(
                            failureKey,
                            action === 'banner' ? 'Unable to update banner.' : 'Unable to update avatar.',
                        ),
                );
            } finally {
                input.value = '';
            }
        });
    };

    const bindTrigger = (trigger: HTMLElement | null, input: HTMLInputElement | null) => {
        if (!trigger || !input || trigger.dataset.profileMediaTriggerBound === '1') {
            return;
        }
        trigger.dataset.profileMediaTriggerBound = '1';
        const open = () => {
            input.value = '';
            input.click();
        };
        trigger.addEventListener('click', open);
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    };

    if (!modalReady) {
        bindInput(
            bannerInput,
            'banner',
            updateBanner,
            'profile_banner_updated',
            'profile_banner_update_failed',
        );
        bindInput(
            avatarInput,
            'avatar',
            updateAvatar,
            'profile_avatar_updated',
            'profile_avatar_update_failed',
        );
        bindTrigger(bannerTrigger, bannerInput);
        bindTrigger(avatarTrigger, avatarInput);
        return;
    }

    let activeAction: ProfileMediaAction | null = null;
    let activeConfig: ProfileMediaConfig | null = null;
    let activeInput: HTMLInputElement | null = null;
    let activeFile: File | null = null;
    let objectUrl: string | null = null;
    let sourceImage: HTMLImageElement | null = null;
    let naturalWidth = 0;
    let naturalHeight = 0;
    let frameWidth = 0;
    let frameHeight = 0;
    let baseScale = 1;
    let scale = 1;
    let zoom = 1;
    let translateX = 0;
    let translateY = 0;
    let dragPointerId: number | null = null;
    let dragStartX = 0;
    let dragStartY = 0;
    let dragOriginX = 0;
    let dragOriginY = 0;
    let hasInitialLayout = false;

    const resetObjectUrl = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const setLoading = (
        isLoading: boolean,
        messageKey = 'profile_media_uploading',
        fallback = 'Uploading your image...',
    ) => {
        if (loadingTextEl) {
            loadingTextEl.textContent = t(messageKey, fallback);
        }
        if (isLoading) {
            panel.scrollTop = 0;
        }
        loadingEl.hidden = !isLoading;
        removeBtn.disabled = isLoading;
        chooseBtn.disabled = isLoading;
        cancelBtn.disabled = isLoading;
        closeBtn.disabled = isLoading;
        applyBtn.disabled = isLoading || !sourceImage;
    };

    const setError = (message: string) => {
        if (!message) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = message;
    };

    const updateZoomLabel = () => {
        zoomValueEl.textContent = `${Math.round(zoom * 100)}%`;
    };

    const clampTranslation = () => {
        if (!sourceImage) {
            return;
        }
        const renderedWidth = naturalWidth * scale;
        const renderedHeight = naturalHeight * scale;
        const minX = Math.min(0, frameWidth - renderedWidth);
        const minY = Math.min(0, frameHeight - renderedHeight);
        translateX = clamp(translateX, minX, 0);
        translateY = clamp(translateY, minY, 0);
    };

    const applyTransform = () => {
        if (!sourceImage) {
            return;
        }
        const renderedWidth = naturalWidth * scale;
        const renderedHeight = naturalHeight * scale;
        imageEl.style.width = `${renderedWidth}px`;
        imageEl.style.height = `${renderedHeight}px`;
        imageEl.style.transform = `translate3d(${translateX}px, ${translateY}px, 0)`;
    };

    const applyZoom = (nextZoom: number) => {
        if (!sourceImage || !frameWidth || !frameHeight) {
            return;
        }
        const prevScale = scale;
        const centerX = (frameWidth / 2 - translateX) / prevScale;
        const centerY = (frameHeight / 2 - translateY) / prevScale;
        zoom = clamp(nextZoom, 1, MAX_ZOOM);
        scale = baseScale * zoom;
        translateX = frameWidth / 2 - centerX * scale;
        translateY = frameHeight / 2 - centerY * scale;
        clampTranslation();
        applyTransform();
        zoomInput.value = String(zoom);
        updateZoomLabel();
    };

    const measureFrame = () => {
        const rect = frameEl.getBoundingClientRect();
        if (!rect.width || !rect.height) {
            requestAnimationFrame(measureFrame);
            return;
        }
        frameWidth = rect.width;
        frameHeight = rect.height;
        if (!sourceImage) {
            return;
        }
        baseScale = Math.max(frameWidth / naturalWidth, frameHeight / naturalHeight);
        if (!hasInitialLayout) {
            scale = baseScale * zoom;
            translateX = (frameWidth - naturalWidth * scale) / 2;
            translateY = (frameHeight - naturalHeight * scale) / 2;
            clampTranslation();
            applyTransform();
            zoomInput.value = String(zoom);
            updateZoomLabel();
            hasInitialLayout = true;
            return;
        }
        applyZoom(zoom);
    };

    const resetEditor = () => {
        imageEl.classList.remove('is-dragging');
        dragPointerId = null;
        activeFile = null;
        sourceImage = null;
        naturalWidth = 0;
        naturalHeight = 0;
        frameWidth = 0;
        frameHeight = 0;
        baseScale = 1;
        scale = 1;
        zoom = 1;
        translateX = 0;
        translateY = 0;
        hasInitialLayout = false;
        zoomInput.value = '1';
        updateZoomLabel();
        imageEl.removeAttribute('src');
        imageEl.style.removeProperty('width');
        imageEl.style.removeProperty('height');
        imageEl.style.removeProperty('transform');
        editorEl.hidden = true;
        applyBtn.disabled = true;
        setError('');
        resetObjectUrl();
    };

    const buildInfoHtml = (config: ProfileMediaConfig) => {
        const hint = t('profile_media_choose_hint', 'Choose an image to continue.');
        const fileRule = withPlaceholders(
            t('profile_media_constraints_file', 'File size: up to :size.'),
            { size: formatBytes(config.maxBytes) },
        );
        const minRule = withPlaceholders(
            t('profile_media_constraints_min', 'Minimum size: :width x :height.'),
            { width: config.minWidth, height: config.minHeight },
        );
        const targetRule = withPlaceholders(
            t('profile_media_constraints_target', 'Saved size: :width x :height.'),
            { width: config.targetWidth, height: config.targetHeight },
        );
        const cropRule = t('profile_media_constraints_crop', 'You can zoom and drag to crop.');
        return [
            `<div><strong>${hint}</strong></div>`,
            `<div>${fileRule}</div>`,
            `<div>${minRule}</div>`,
            `<div>${targetRule}</div>`,
            `<div>${cropRule}</div>`,
        ].join('');
    };

    const isCustomAvatar = () => {
        const profileAvatar = document.querySelector<HTMLImageElement>('[data-profile-avatar-image]');
        const src = (profileAvatar?.getAttribute('src') ?? '').trim();
        if (!src) {
            return false;
        }
        if (src.startsWith('data:')) {
            return false;
        }
        return !src.includes('avatar-default.svg');
    };

    const resetBannerToDefault = () => {
        banner.dataset.profileBannerImage = '';
        banner.classList.remove('has-custom-banner');
        banner.style.removeProperty('--profile-banner-image');
        const glow = banner.querySelector<HTMLElement>('[data-profile-banner-glow]');
        const scribble = banner.querySelector<HTMLElement>('[data-profile-banner-scribble]');
        if (glow) {
            glow.innerHTML = '';
        }
        if (scribble) {
            scribble.innerHTML = '';
        }
        delete banner.dataset.profileBannerBound;
        setupProfileBanner();
    };

    const resetAvatarToDefault = () => {
        const fallbackUrl = `${appUrl}/images/avatar-default.svg`;
        const avatars = Array.from(document.querySelectorAll<HTMLImageElement>('img.avatar'));
        avatars.forEach((avatar) => {
            const alt = (avatar.getAttribute('alt') ?? '').trim();
            if (profileName && alt && alt !== profileName) {
                return;
            }
            avatar.src = fallbackUrl;
            avatar.dataset.avatarAuto = '1';
            delete avatar.dataset.avatarGenerated;
            const name = (avatar.dataset.avatarName ?? alt ?? profileName).trim();
            applyScribbleAvatar(avatar, name);
        });
    };

    const openModal = (action: ProfileMediaAction) => {
        activeAction = action;
        activeConfig = mediaConfigs[action];
        activeInput = action === 'banner' ? bannerInput : avatarInput;
        modal.dataset.profileMediaAction = action;
        resetEditor();
        setLoading(false);
        titleEl.textContent = t(activeConfig.titleKey, action === 'banner' ? 'Update banner' : 'Update avatar');
        infoEl.innerHTML = buildInfoHtml(activeConfig);
        frameEl.style.setProperty('--media-aspect', `${activeConfig.targetWidth} / ${activeConfig.targetHeight}`);
        removeBtn.hidden = action === 'banner' ? !Boolean((banner.dataset.profileBannerImage ?? '').trim()) : !isCustomAvatar();
        modal.hidden = false;
        requestAnimationFrame(measureFrame);
    };

    const closeModal = () => {
        resetEditor();
        setLoading(false);
        modal.hidden = true;
        delete modal.dataset.profileMediaAction;
        removeBtn.hidden = true;
        activeAction = null;
        activeConfig = null;
        activeInput = null;
    };

    const validateFile = (file: File, config: ProfileMediaConfig) => {
        if (!file.type.startsWith('image/')) {
            return t('profile_media_error_type', 'Unsupported file type.');
        }
        if (file.size > config.maxBytes) {
            return t('profile_media_error_file_size', 'File is too large.');
        }
        return '';
    };

    const handleFileSelection = async (file: File) => {
        if (!activeConfig) {
            return;
        }
        const validationError = validateFile(file, activeConfig);
        if (validationError) {
            setError(validationError);
            toast.show(validationError);
            return;
        }

        resetObjectUrl();
        objectUrl = URL.createObjectURL(file);

        try {
            const image = await loadImage(objectUrl);
            if (image.naturalWidth < activeConfig.minWidth || image.naturalHeight < activeConfig.minHeight) {
                const message = t('profile_media_error_dimensions', 'Image is too small.');
                setError(message);
                toast.show(message);
                resetObjectUrl();
                return;
            }
            activeFile = file;
            sourceImage = image;
            naturalWidth = image.naturalWidth;
            naturalHeight = image.naturalHeight;
            hasInitialLayout = false;
            imageEl.src = objectUrl;
            imageEl.alt = profileName ? `${profileName} media` : 'Profile media';
            editorEl.hidden = false;
            requestAnimationFrame(measureFrame);
            applyBtn.disabled = false;
            setError('');
        } catch (error) {
            const message = t('profile_media_error_read', 'Unable to read this image.');
            setError(message);
            toast.show(message);
            resetObjectUrl();
        }
    };

    const getSourceRect = () => {
        if (!sourceImage) {
            return null;
        }
        const sourceWidth = Math.min(naturalWidth, frameWidth / scale);
        const sourceHeight = Math.min(naturalHeight, frameHeight / scale);
        const maxX = Math.max(0, naturalWidth - sourceWidth);
        const maxY = Math.max(0, naturalHeight - sourceHeight);
        const sourceX = clamp(-translateX / scale, 0, maxX);
        const sourceY = clamp(-translateY / scale, 0, maxY);
        return {
            x: sourceX,
            y: sourceY,
            width: sourceWidth,
            height: sourceHeight,
        };
    };

    const cropToBlob = async () => {
        if (!sourceImage || !activeConfig || !activeFile) {
            return null;
        }
        const rect = getSourceRect();
        if (!rect) {
            return null;
        }
        const canvas = document.createElement('canvas');
        canvas.width = activeConfig.targetWidth;
        canvas.height = activeConfig.targetHeight;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return null;
        }
        ctx.drawImage(
            sourceImage,
            rect.x,
            rect.y,
            rect.width,
            rect.height,
            0,
            0,
            activeConfig.targetWidth,
            activeConfig.targetHeight,
        );
        const blob =
            (await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/webp', 0.9))) ??
            (await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png', 1)));
        if (!blob) {
            return null;
        }
        if (blob.type === 'image/webp') {
            return fileWithExtension(activeFile, 'webp', blob.type, blob);
        }
        return fileWithExtension(activeFile, 'png', blob.type || 'image/png', blob);
    };

    const handleApply = async () => {
        if (!activeAction || !activeConfig || !activeFile || !sourceImage) {
            return;
        }
        try {
            setLoading(true);
            const croppedFile = await cropToBlob();
            if (!croppedFile) {
                throw new Error(t('profile_media_error_crop', 'Unable to crop this image.'));
            }
            const uploadedUrl = await upload(croppedFile, activeAction);
            if (activeAction === 'banner') {
                updateBanner(uploadedUrl);
                toast.show(t('profile_banner_updated', 'Banner updated.'));
            } else {
                updateAvatar(uploadedUrl);
                toast.show(t('profile_avatar_updated', 'Avatar updated.'));
            }
            closeModal();
        } catch (error) {
            const message =
                error instanceof Error && error.message
                    ? error.message
                    : t(
                          activeAction === 'banner' ? 'profile_banner_update_failed' : 'profile_avatar_update_failed',
                          activeAction === 'banner' ? 'Unable to update banner.' : 'Unable to update avatar.',
                      );
            toast.show(message);
            setError(message);
            setLoading(false);
        }
    };

    const handleRemove = async () => {
        if (!activeAction) {
            return;
        }
        const confirmText = t('confirm_action', 'Are you sure?');
        if (!window.confirm(confirmText)) {
            return;
        }
        try {
            setError('');
            setLoading(true, 'profile_media_removing', 'Removing...');
            const response = await fetch(toFormUrl(appUrl, slug, activeAction), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            if (!response.ok) {
                const message = await readErrorMessage(response);
                throw new Error(message || response.statusText);
            }
            if (activeAction === 'banner') {
                resetBannerToDefault();
                toast.show(t('profile_banner_removed', 'Banner removed.'));
            } else {
                resetAvatarToDefault();
                toast.show(t('profile_avatar_removed', 'Avatar removed.'));
            }
            closeModal();
        } catch (error) {
            const message =
                error instanceof Error && error.message
                    ? error.message
                    : t(
                          activeAction === 'banner'
                              ? 'profile_banner_remove_failed'
                              : 'profile_avatar_remove_failed',
                          activeAction === 'banner'
                              ? 'Unable to remove banner.'
                              : 'Unable to remove avatar.',
                      );
            toast.show(message);
            setError(message);
            setLoading(false);
        }
    };

    const bindModalTrigger = (trigger: HTMLElement | null, action: ProfileMediaAction) => {
        if (!trigger || trigger.dataset.profileMediaTriggerBound === '1') {
            return;
        }
        trigger.dataset.profileMediaTriggerBound = '1';
        const open = () => openModal(action);
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    };

    if (bannerInput && bannerInput.dataset.profileMediaBound !== '1') {
        bannerInput.dataset.profileMediaBound = '1';
        bannerInput.addEventListener('change', () => {
            const file = bannerInput.files?.[0];
            if (!file) {
                return;
            }
            void handleFileSelection(file);
            bannerInput.value = '';
        });
    }

    if (avatarInput && avatarInput.dataset.profileMediaBound !== '1') {
        avatarInput.dataset.profileMediaBound = '1';
        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files?.[0];
            if (!file) {
                return;
            }
            void handleFileSelection(file);
            avatarInput.value = '';
        });
    }

    removeBtn.addEventListener('click', () => void handleRemove());

    chooseBtn.addEventListener('click', () => {
        if (!activeInput) {
            return;
        }
        activeInput.value = '';
        activeInput.click();
    });

    cancelBtn.addEventListener('click', () => closeModal());
    closeBtn.addEventListener('click', () => closeModal());
    applyBtn.addEventListener('click', () => void handleApply());

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    zoomInput.addEventListener('input', () => {
        const next = Number.parseFloat(zoomInput.value);
        if (!Number.isFinite(next)) {
            return;
        }
        applyZoom(next);
    });

    frameEl.addEventListener('pointerdown', (event) => {
        if (!sourceImage) {
            return;
        }
        dragPointerId = event.pointerId;
        dragStartX = event.clientX;
        dragStartY = event.clientY;
        dragOriginX = translateX;
        dragOriginY = translateY;
        imageEl.classList.add('is-dragging');
        frameEl.setPointerCapture(event.pointerId);
    });

    frameEl.addEventListener('pointermove', (event) => {
        if (!sourceImage || dragPointerId !== event.pointerId) {
            return;
        }
        translateX = dragOriginX + (event.clientX - dragStartX);
        translateY = dragOriginY + (event.clientY - dragStartY);
        clampTranslation();
        applyTransform();
    });

    const stopDragging = (event: PointerEvent) => {
        if (dragPointerId !== event.pointerId) {
            return;
        }
        dragPointerId = null;
        imageEl.classList.remove('is-dragging');
        if (frameEl.hasPointerCapture(event.pointerId)) {
            frameEl.releasePointerCapture(event.pointerId);
        }
    };

    frameEl.addEventListener('pointerup', stopDragging);
    frameEl.addEventListener('pointercancel', stopDragging);
    frameEl.addEventListener('lostpointercapture', (event) => stopDragging(event as PointerEvent));

    window.addEventListener('resize', () => {
        if (!modal.hidden && sourceImage) {
            requestAnimationFrame(measureFrame);
        }
    });

    bindModalTrigger(bannerTrigger, 'banner');
    bindModalTrigger(avatarTrigger, 'avatar');
};
