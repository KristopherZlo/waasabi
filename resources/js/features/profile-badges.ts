import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';

type BadgeCatalogEntry = {
    key: string;
    name: string;
    description: string;
    icon: string;
};

type UserBadgeEntry = {
    id: number;
    key: string;
    label: string;
    description?: string;
    reason?: string;
    issued_at?: string;
    icon?: string;
};

const readJsonScript = <T>(selector: string): T | null => {
    const element = document.querySelector<HTMLScriptElement>(selector);
    if (!element?.textContent) {
        return null;
    }
    try {
        return JSON.parse(element.textContent) as T;
    } catch {
        return null;
    }
};

const setModalOpen = (modal: HTMLElement, open: boolean) => {
    modal.hidden = !open;
    document.body.classList.toggle('is-locked', open);
};

export const setupProfileBadges = () => {
    const grantTrigger = document.querySelector<HTMLElement>('[data-profile-action="grant-badge"]');
    const revokeTrigger = document.querySelector<HTMLElement>('[data-profile-action="revoke-badge"]');
    const badgeButtons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-badge-view]'));

    const viewModal = document.querySelector<HTMLElement>('[data-badge-view-modal]');
    const viewPanel = viewModal?.querySelector<HTMLElement>('[data-badge-view-panel]') ?? null;
    const viewClose = viewModal?.querySelector<HTMLElement>('[data-badge-view-close]') ?? null;
    const viewIcon = viewModal?.querySelector<HTMLImageElement>('[data-badge-view-icon]') ?? null;
    const viewLabel = viewModal?.querySelector<HTMLElement>('[data-badge-view-label]') ?? null;
    const viewDescription = viewModal?.querySelector<HTMLElement>('[data-badge-view-description]') ?? null;
    const viewIssued = viewModal?.querySelector<HTMLElement>('[data-badge-view-issued]') ?? null;
    let viewBurst = viewModal?.querySelector<HTMLElement>('[data-badge-view-burst]') ?? null;

    const hasAdminTriggers = Boolean(grantTrigger || revokeTrigger);
    const hasViewModal = Boolean(
        viewModal && viewPanel && viewClose && viewIcon && viewLabel && viewDescription && viewIssued
    );

    if (!hasAdminTriggers && !hasViewModal) {
        return;
    }

    const banner = document.querySelector<HTMLElement>('.profile-banner');
    const profileSlug = banner?.dataset.profileUserSlug ?? '';
    if (hasAdminTriggers && !profileSlug) {
        return;
    }

    const normalizeIcon = (icon: string) => {
        if (!icon) {
            return '';
        }
        if (/^https?:\/\//i.test(icon) || icon.startsWith('data:')) {
            return icon;
        }
        const normalized = icon.startsWith('/') ? icon : `/${icon}`;
        return appUrl ? `${appUrl}${normalized}` : normalized;
    };

    const catalog = (readJsonScript<BadgeCatalogEntry[]>('[data-badge-catalog]') ?? []).map((entry) => ({
        ...entry,
        icon: normalizeIcon(entry.icon),
    }));
    const normalizeBadgeEntry = (entry: UserBadgeEntry): UserBadgeEntry => ({
        ...entry,
        icon: normalizeIcon(entry.icon ?? ''),
    });
    let userBadges = (readJsonScript<UserBadgeEntry[]>('[data-user-badges]') ?? []).map(normalizeBadgeEntry);

    const grantModal = document.querySelector<HTMLElement>('[data-badge-modal]');
    const grantPanel = grantModal?.querySelector<HTMLElement>('[data-badge-panel]') ?? null;
    const grantClose = grantModal?.querySelector<HTMLElement>('[data-badge-close]') ?? null;
    const badgeGrid = grantModal?.querySelector<HTMLElement>('[data-badge-grid]') ?? null;
    const badgeForm = grantModal?.querySelector<HTMLFormElement>('[data-badge-form]') ?? null;
    const badgeNameInput = grantModal?.querySelector<HTMLInputElement>('[data-badge-name]') ?? null;
    const badgeDescriptionInput = grantModal?.querySelector<HTMLTextAreaElement>('[data-badge-description]') ?? null;
    const badgeReasonInput = grantModal?.querySelector<HTMLInputElement>('[data-badge-reason]') ?? null;
    const badgeSubmit = grantModal?.querySelector<HTMLButtonElement>('[data-badge-submit]') ?? null;

    const revokeModal = document.querySelector<HTMLElement>('[data-badge-revoke-modal]');
    const revokePanel = revokeModal?.querySelector<HTMLElement>('[data-badge-revoke-panel]') ?? null;
    const revokeClose = revokeModal?.querySelector<HTMLElement>('[data-badge-revoke-close]') ?? null;
    const revokeList = revokeModal?.querySelector<HTMLElement>('[data-badge-revoke-list]') ?? null;
    const revokeEmpty = revokeModal?.querySelector<HTMLElement>('[data-badge-revoke-empty]') ?? null;

    const profileHeader = document.querySelector<HTMLElement>('.profile-header');
    const identity = profileHeader?.querySelector<HTMLElement>('.profile-header__identity') ?? null;
    let badgeContainer = document.querySelector<HTMLElement>('.profile-badges');
    let badgeList = badgeContainer?.querySelector<HTMLElement>('.profile-badges__list') ?? null;

    const badgeReason = (badge: UserBadgeEntry) => (badge.reason?.trim() || badge.description?.trim() || '');
    const badgeTooltip = (badge: UserBadgeEntry) => {
        const issuedAt = badge.issued_at?.trim() || '';
        const reason = badgeReason(badge);
        const parts = [issuedAt, reason].filter((part) => part !== '');
        return parts.join(' - ') || badge.label;
    };

    const ensureBadgeList = () => {
        if (badgeList) {
            return badgeList;
        }
        if (!profileHeader) {
            return null;
        }
        badgeContainer = document.createElement('div');
        badgeContainer.className = 'profile-badges';
        badgeList = document.createElement('div');
        badgeList.className = 'profile-badges__list';
        badgeContainer.appendChild(badgeList);
        if (identity?.nextSibling) {
            profileHeader.insertBefore(badgeContainer, identity.nextSibling);
        } else {
            profileHeader.appendChild(badgeContainer);
        }
        return badgeList;
    };

    let selectedBadge: BadgeCatalogEntry | null = null;

    const selectBadge = (entry: BadgeCatalogEntry, button: HTMLElement) => {
        selectedBadge = entry;
        badgeGrid?.querySelectorAll<HTMLElement>('.badge-option').forEach((option) => {
            option.classList.toggle('is-active', option === button);
        });
        if (badgeNameInput) {
            badgeNameInput.value = entry.name ?? '';
        }
        if (badgeDescriptionInput) {
            badgeDescriptionInput.value = entry.description ?? '';
        }
        if (badgeReasonInput) {
            badgeReasonInput.value = '';
        }
    };

    const buildCatalog = () => {
        if (!badgeGrid) {
            return;
        }
        badgeGrid.innerHTML = '';
        catalog.forEach((entry, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'badge-option';
            button.innerHTML = `
                <img src="${entry.icon}" alt="${entry.name}">
                <div>
                    <div class="badge-option__name">${entry.name}</div>
                    <div class="badge-option__desc">${entry.description}</div>
                </div>
            `;
            button.addEventListener('click', () => selectBadge(entry, button));
            badgeGrid.appendChild(button);
            if (index === 0) {
                selectBadge(entry, button);
            }
        });
    };

    function closeViewModal() {
        if (viewModal && !viewModal.hidden) {
            setModalOpen(viewModal, false);
        }
        clearBurst();
    }

    const defaultGlowRgb = '78, 161, 255';
    let glowRequestId = 0;

    const setGlowReady = (ready: boolean) => {
        if (!viewPanel) {
            return;
        }
        viewPanel.classList.toggle('is-glow-ready', ready);
    };

    const setGlowRgb = (rgb: string) => {
        if (!viewModal) {
            return;
        }
        viewModal.style.setProperty('--badge-glow-rgb', rgb);
    };

    const averageRgbFromImage = (img: HTMLImageElement): string | null => {
        const width = img.naturalWidth || img.width;
        const height = img.naturalHeight || img.height;
        if (!width || !height) {
            return null;
        }
        const sampleSize = 48;
        const scale = Math.min(sampleSize / width, sampleSize / height, 1);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return null;
        }
        try {
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
            let r = 0;
            let g = 0;
            let b = 0;
            let weight = 0;
            for (let i = 0; i < data.length; i += 4) {
                const alpha = data[i + 3] / 255;
                if (alpha < 0.05) {
                    continue;
                }
                r += data[i] * alpha;
                g += data[i + 1] * alpha;
                b += data[i + 2] * alpha;
                weight += alpha;
            }
            if (weight <= 0) {
                return null;
            }
            return `${Math.round(r / weight)}, ${Math.round(g / weight)}, ${Math.round(b / weight)}`;
        } catch {
            return null;
        }
    };

    const updateGlowFromIcon = (src: string) => {
        if (!viewModal) {
            return;
        }
        glowRequestId += 1;
        const requestId = glowRequestId;
        setGlowReady(false);
        if (!src) {
            setGlowRgb(defaultGlowRgb);
            requestAnimationFrame(() => setGlowReady(true));
            return;
        }
        const probe = new Image();
        probe.crossOrigin = 'anonymous';
        probe.decoding = 'async';
        probe.onload = () => {
            if (requestId !== glowRequestId) {
                return;
            }
            const rgb = averageRgbFromImage(probe);
            setGlowRgb(rgb ?? defaultGlowRgb);
            requestAnimationFrame(() => setGlowReady(true));
        };
        probe.onerror = () => {
            if (requestId !== glowRequestId) {
                return;
            }
            setGlowRgb(defaultGlowRgb);
            requestAnimationFrame(() => setGlowReady(true));
        };
        probe.src = src;
    };

    let burstTimeout: number | null = null;

    const clearBurst = () => {
        if (burstTimeout) {
            window.clearTimeout(burstTimeout);
            burstTimeout = null;
        }
        if (viewBurst) {
            viewBurst.innerHTML = '';
        }
    };

    const ensureBurstContainer = () => {
        if (viewBurst) {
            return viewBurst;
        }
        const media = viewModal?.querySelector<HTMLElement>('.badge-view-card__media') ?? null;
        if (!media) {
            return null;
        }
        const burst = document.createElement('div');
        burst.className = 'badge-view-card__burst';
        burst.setAttribute('aria-hidden', 'true');
        burst.dataset.badgeViewBurst = 'true';
        if (viewIcon && media.contains(viewIcon)) {
            media.insertBefore(burst, viewIcon);
        } else {
            media.appendChild(burst);
        }
        viewBurst = burst;
        return viewBurst;
    };

    const spawnBurst = () => {
        const burst = ensureBurstContainer();
        if (!burst) {
            return;
        }
        clearBurst();
        const starSrc = normalizeIcon('/images/star.svg');
        const starCount = 16;
        const fragment = document.createDocumentFragment();
        let maxDuration = 0;
        for (let i = 0; i < starCount; i += 1) {
            const star = document.createElement('img');
            star.className = 'badge-view-card__star';
            star.src = starSrc;
            star.alt = '';
            star.setAttribute('aria-hidden', 'true');

            const size = 8 + Math.random() * 10;
            const angle = Math.random() * Math.PI * 2;
            const distance = 40 + Math.random() * 80;
            const x = Math.cos(angle) * distance;
            const y = Math.sin(angle) * distance;
            const rotation = (60 + Math.random() * 180) * (Math.random() < 0.5 ? -1 : 1);
            const delay = Math.random() * 120;
            const duration = 1400 + Math.random() * 600;

            star.style.width = `${size.toFixed(1)}px`;
            star.style.height = `${size.toFixed(1)}px`;
            star.style.setProperty('--burst-x', `${x.toFixed(1)}px`);
            star.style.setProperty('--burst-y', `${y.toFixed(1)}px`);
            star.style.setProperty('--burst-rotate', `${rotation.toFixed(1)}deg`);
            star.style.setProperty('--burst-delay', `${delay.toFixed(0)}ms`);
            star.style.setProperty('--burst-duration', `${duration.toFixed(0)}ms`);

            maxDuration = Math.max(maxDuration, delay + duration);
            fragment.appendChild(star);
        }

        burst.appendChild(fragment);
        burstTimeout = window.setTimeout(() => {
            clearBurst();
        }, maxDuration + 120);
    };

    function openViewModal(badgeButton: HTMLElement) {
        if (!hasViewModal || !viewModal || !viewIcon || !viewLabel || !viewDescription || !viewIssued) {
            return;
        }
        const icon = normalizeIcon(badgeButton.dataset.badgeIcon ?? '');
        const label = badgeButton.dataset.badgeLabel ?? '';
        const description = (badgeButton.dataset.badgeDescription ?? '').trim();
        const issuedAt = (badgeButton.dataset.badgeIssued ?? '').trim();

        viewIcon.src = icon;
        viewIcon.alt = label;
        viewLabel.textContent = label;
        viewDescription.textContent = description || t('badge_view_no_description', 'No description provided.');
        viewIssued.textContent = issuedAt || t('badge_view_issued_unknown', 'Unknown');
        updateGlowFromIcon(icon);

        setModalOpen(viewModal, true);
        spawnBurst();
    }

    function bindBadgeButton(button: HTMLButtonElement) {
        if (!hasViewModal) {
            return;
        }
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openViewModal(button);
        });
        button.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openViewModal(button);
            }
        });
    }

    function createBadgeButton(badge: UserBadgeEntry): HTMLButtonElement {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'profile-badge';
        const reason = badgeReason(badge);
        const issuedAt = badge.issued_at?.trim() || '';
        button.dataset.badgeView = '';
        button.dataset.badgeIcon = badge.icon ?? '';
        button.dataset.badgeLabel = badge.label;
        button.dataset.badgeDescription = reason;
        button.dataset.badgeIssued = issuedAt;
        button.dataset.tooltip = badgeTooltip(badge);
        button.setAttribute('aria-label', badge.label);

        const img = document.createElement('img');
        img.src = badge.icon ?? '';
        img.alt = badge.label;
        button.appendChild(img);

        bindBadgeButton(button);

        return button;
    }

    function renderBadgeList() {
        if (!userBadges.length) {
            badgeContainer?.remove();
            badgeContainer = null;
            badgeList = null;
            closeViewModal();
            return;
        }

        const list = ensureBadgeList();
        if (!list) {
            return;
        }

        list.innerHTML = '';
        userBadges.forEach((badge) => {
            list.appendChild(createBadgeButton(badge));
        });
    }

    function syncUserBadges(nextBadges: UserBadgeEntry[] | null | undefined) {
        userBadges = (nextBadges ?? []).map(normalizeBadgeEntry);
        renderBadgeList();
        if (revokeModal && !revokeModal.hidden) {
            buildRevokeList();
        }
    }

    function removeBadgeFromState(badgeId: number) {
        userBadges = userBadges.filter((badge) => badge.id !== badgeId);
        renderBadgeList();
        if (revokeModal && !revokeModal.hidden) {
            buildRevokeList();
        }
    }

    const buildRevokeList = () => {
        if (!revokeList || !revokeEmpty) {
            return;
        }
        revokeList.innerHTML = '';
        if (!userBadges.length) {
            revokeEmpty.hidden = false;
            return;
        }
        revokeEmpty.hidden = true;
        userBadges.forEach((badge) => {
            const item = document.createElement('div');
            item.className = 'badge-revoke-item';
            const reason = badge.reason || badge.description || '';
            item.innerHTML = `
                <div class="badge-revoke-info">
                    <img src="${badge.icon ?? ''}" alt="${badge.label}">
                    <div>
                        <div class="badge-revoke-title">${badge.label}</div>
                        <div class="badge-revoke-reason">${reason}</div>
                    </div>
                </div>
                <button type="button" class="ghost-btn" data-badge-revoke-button="${badge.id}">
                    ${t('badge_revoke_cta', 'Remove')}
                </button>
            `;
            const removeButton = item.querySelector<HTMLButtonElement>('[data-badge-revoke-button]');
            removeButton?.addEventListener('click', async () => {
                if (!csrfToken) {
                    return;
                }
                removeButton.disabled = true;
                try {
                    const response = await fetch(`${appUrl}/profile/${profileSlug}/badges/${badge.id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                        },
                    });
                    const payload = await response.json().catch(() => null);
                    if (response.ok) {
                        const nextBadges = Array.isArray(payload?.badges) ? payload.badges : null;
                        if (nextBadges) {
                            syncUserBadges(nextBadges);
                        } else {
                            removeBadgeFromState(badge.id);
                        }
                        toast.show(t('badge_revoked', 'Badge removed.'));
                        return;
                    }
                    toast.show(t('badge_revoke_failed', 'Unable to remove badge.'));
                    removeButton.disabled = false;
                } catch {
                    toast.show(t('badge_revoke_failed', 'Unable to remove badge.'));
                    removeButton.disabled = false;
                }
            });
            revokeList.appendChild(item);
        });
    };

    if (hasViewModal) {
        badgeButtons.forEach(bindBadgeButton);

        viewClose?.addEventListener('click', closeViewModal);
        viewPanel?.addEventListener('click', (event) => event.stopPropagation());
        viewModal?.addEventListener('click', (event) => {
            if (event.target === viewModal) {
                closeViewModal();
            }
        });
    }

    const openGrantModal = () => {
        if (!grantModal) {
            return;
        }
        buildCatalog();
        setModalOpen(grantModal, true);
    };

    const openRevokeModal = () => {
        if (!revokeModal) {
            return;
        }
        buildRevokeList();
        setModalOpen(revokeModal, true);
    };

    grantTrigger?.addEventListener('click', (event) => {
        event.preventDefault();
        openGrantModal();
    });

    revokeTrigger?.addEventListener('click', (event) => {
        event.preventDefault();
        openRevokeModal();
    });

    grantClose?.addEventListener('click', () => grantModal && setModalOpen(grantModal, false));
    revokeClose?.addEventListener('click', () => revokeModal && setModalOpen(revokeModal, false));

    grantModal?.addEventListener('click', (event) => {
        if (event.target === grantModal) {
            setModalOpen(grantModal, false);
        }
    });

    revokeModal?.addEventListener('click', (event) => {
        if (event.target === revokeModal) {
            setModalOpen(revokeModal, false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (viewModal && !viewModal.hidden) {
                closeViewModal();
            }
            if (grantModal && !grantModal.hidden) {
                setModalOpen(grantModal, false);
            }
            if (revokeModal && !revokeModal.hidden) {
                setModalOpen(revokeModal, false);
            }
        }
    });

    badgeForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedBadge) {
            toast.show(t('badge_select', 'Select a badge first.'));
            return;
        }
        if (!csrfToken) {
            return;
        }
        if (badgeSubmit) {
            badgeSubmit.disabled = true;
        }
        const rawName = badgeNameInput?.value.trim() || '';
        const rawDescription = badgeDescriptionInput?.value.trim() || '';
        const payload = {
            badge_key: selectedBadge.key,
            name: rawName === (selectedBadge.name ?? '') ? null : rawName || null,
            description: rawDescription === (selectedBadge.description ?? '') ? null : rawDescription || null,
            reason: badgeReasonInput?.value.trim() || null,
        };
        try {
            const response = await fetch(`${appUrl}/profile/${profileSlug}/badges`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => null);
            if (response.ok) {
                const nextBadges = Array.isArray(result?.badges) ? result.badges : null;
                if (nextBadges) {
                    syncUserBadges(nextBadges);
                }
                toast.show(t('badge_granted', 'Badge granted.'));
                if (grantModal) {
                    setModalOpen(grantModal, false);
                }
                return;
            }
            toast.show(t('badge_grant_failed', 'Unable to grant badge.'));
        } catch {
            toast.show(t('badge_grant_failed', 'Unable to grant badge.'));
        } finally {
            if (badgeSubmit) {
                badgeSubmit.disabled = false;
            }
        }
    });
};
