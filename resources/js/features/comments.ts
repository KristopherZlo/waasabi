import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { setupIcons, setupImageFallbacks, setupScribbleAvatars, applyScribbleAvatar } from '../core/media';
import { getRoleKey } from '../core/roles';
import { normalizeCommentVote } from '../core/votes';
import { toast } from '../core/toast';
import { bindActionMenus } from '../ui/action-menus';
import { bindActionToggles } from '../ui/action-toggles';
import { setupReportModal } from '../ui/report';

type CommentEntry = {
    author: string;
    authorSlug?: string;
    text: string;
    createdAt: number;
    useful: number;
    id?: number | string;
    section?: string;
    role?: string;
    roleLabel?: string;
    preview?: string;
    isAuthor?: boolean;
};

type CommentNodeOptions = {
    threaded?: boolean;
    variant?: 'thread' | 'reply';
    anchor?: string;
};

let commentChunksUpdatedHandler: (() => void) | null = null;

const parseJson = <T>(value: string | null, fallback: T) => {
    if (!value) {
        return fallback;
    }
    try {
        return JSON.parse(value) as T;
    } catch {
        return fallback;
    }
};

const applyCommentSort = (list: HTMLElement, mode: string) => {
    const items = Array.from(list.querySelectorAll<HTMLElement>('[data-comment-item]'));
    if (!items.length) {
        list.dataset.commentSort = mode;
        return;
    }
    const sorted = [...items].sort((a, b) => {
        const createdA = Number(a.dataset.commentCreated ?? a.dataset.commentOrder ?? 0);
        const createdB = Number(b.dataset.commentCreated ?? b.dataset.commentOrder ?? 0);
        const usefulA = Number(a.dataset.commentUseful ?? 0);
        const usefulB = Number(b.dataset.commentUseful ?? 0);
        if (mode === 'best') {
            return usefulB - usefulA || createdB - createdA;
        }
        return createdB - createdA;
    });
    sorted.forEach((item) => list.appendChild(item));
    list.dataset.commentSort = mode;
};

const getCommentVoteKey = (slug: string) => `commentVotes:${slug}`;
const getCommentVoteMap = (slug: string) =>
    parseJson<Record<string, number>>(localStorage.getItem(getCommentVoteKey(slug)), {});
const setCommentVoteMap = (slug: string, map: Record<string, number>) => {
    localStorage.setItem(getCommentVoteKey(slug), JSON.stringify(map));
};

const applyCommentVoteState = (commentEl: HTMLElement, voteValue: number) => {
    const countEl = commentEl.querySelector<HTMLElement>('.vote-count');
    if (!countEl) {
        return;
    }
    const base =
        countEl.dataset.baseCount !== undefined ? Number(countEl.dataset.baseCount) : Number(countEl.textContent ?? 0);
    if (countEl.dataset.baseCount === undefined) {
        countEl.dataset.baseCount = String(base);
    }
    const nextCount = base + voteValue;
    countEl.textContent = String(nextCount);
    commentEl.dataset.commentUseful = String(nextCount);
    const upBtn = commentEl.querySelector<HTMLButtonElement>('[data-comment-vote="up"]');
    const downBtn = commentEl.querySelector<HTMLButtonElement>('[data-comment-vote="down"]');
    if (upBtn) {
        upBtn.classList.toggle('is-active', voteValue === 1);
        upBtn.setAttribute('aria-pressed', voteValue === 1 ? 'true' : 'false');
    }
    if (downBtn) {
        downBtn.classList.toggle('is-active', voteValue === -1);
        downBtn.setAttribute('aria-pressed', voteValue === -1 ? 'true' : 'false');
    }
};

const syncThreadedCommentState = (list: HTMLElement, slug: string) => {
    if (!slug) {
        return;
    }
    const votes = getCommentVoteMap(slug);
    const commentItems = Array.from(list.querySelectorAll<HTMLElement>('.comment'));
    commentItems.forEach((commentEl) => {
        const anchor = commentEl.dataset.commentAnchor ?? commentEl.id ?? '';
        if (!anchor) {
            return;
        }
        const voteValue = normalizeCommentVote(votes[anchor]);
        applyCommentVoteState(commentEl, voteValue);
    });
};

export const setupCommentSorts = () => {
    const lists = Array.from(document.querySelectorAll<HTMLElement>('[data-comment-list]'));
    if (!lists.length) {
        return;
    }

    lists.forEach((list) => {
        if (list.dataset.commentSortBound === '1') {
            return;
        }
        const panel = list.closest<HTMLElement>('[data-tab-panel="comments"]') ?? list.parentElement;
        if (!panel) {
            return;
        }
        const sortButtons = Array.from(panel.querySelectorAll<HTMLButtonElement>('[data-comment-sort]'));
        if (!sortButtons.length) {
            return;
        }

        list.dataset.commentSortBound = '1';

        const applyActive = (mode: string) => {
            sortButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.commentSort === mode);
            });
        };

        const setSort = (mode: string) => {
            applyActive(mode);
            applyCommentSort(list, mode);
        };

        sortButtons.forEach((button) => {
            if (button.dataset.commentSortBound === '1') {
                return;
            }
            button.dataset.commentSortBound = '1';
            button.addEventListener('click', () => {
                setSort(button.dataset.commentSort ?? 'new');
            });
        });

        const initial =
            sortButtons.find((button) => button.classList.contains('is-active'))?.dataset.commentSort ?? 'new';
        setSort(initial);
    });
};

export const setupCommentChunks = () => {
    const buttons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-comment-more]'));
    if (!buttons.length) {
        if (commentChunksUpdatedHandler) {
            document.removeEventListener('comments:updated', commentChunksUpdatedHandler);
            commentChunksUpdatedHandler = null;
        }
        return;
    }

    const resolveList = (button: HTMLElement) => {
        const panel = button.closest<HTMLElement>('[data-tab-panel="comments"]') ?? button.parentElement;
        return panel?.querySelector<HTMLElement>('[data-comment-list]') ?? null;
    };

    const updateButton = (button: HTMLButtonElement, list: HTMLElement) => {
        const fallbackOffset = list.querySelectorAll('[data-comment-item]').length;
        const offsetValue = Number(list.dataset.commentsOffset ?? fallbackOffset);
        const totalValue = Number(list.dataset.commentsTotal ?? 0);
        const offset = Number.isFinite(offsetValue) ? offsetValue : fallbackOffset;
        const total = Number.isFinite(totalValue) ? totalValue : 0;
        button.hidden = !(total > 0 && offset < total);
    };

    const syncButtons = () => {
        buttons.forEach((button) => {
            const list = resolveList(button);
            if (list) {
                updateButton(button, list);
            }
        });
    };

    syncButtons();
    if (commentChunksUpdatedHandler) {
        document.removeEventListener('comments:updated', commentChunksUpdatedHandler);
    }
    commentChunksUpdatedHandler = () => syncButtons();
    document.addEventListener('comments:updated', commentChunksUpdatedHandler);

    buttons.forEach((button) => {
        if (button.dataset.commentMoreBound === '1') {
            return;
        }
        button.dataset.commentMoreBound = '1';
        const list = resolveList(button);
        if (!list) {
            return;
        }

        button.addEventListener('click', async () => {
            const endpoint = list.dataset.commentsEndpoint ?? '';
            if (!endpoint) {
                return;
            }
            const fallbackOffset = list.querySelectorAll('[data-comment-item]').length;
            const offsetValue = Number(list.dataset.commentsOffset ?? fallbackOffset);
            const offset = Number.isFinite(offsetValue) ? offsetValue : fallbackOffset;
            const limit = Math.max(1, Number(list.dataset.commentsLimit ?? 15) || 15);
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', String(limit));

            button.disabled = true;
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) {
                    return;
                }
                const data = (await response.json()) as {
                    items?: string[];
                    next_offset?: number;
                    total?: number;
                };
                const items = Array.isArray(data.items) ? data.items : [];
                if (items.length) {
                    const fragment = document.createDocumentFragment();
                    items.forEach((html) => {
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = html.trim();
                        const element = wrapper.firstElementChild as HTMLElement | null;
                        if (element) {
                            fragment.appendChild(element);
                        }
                    });
                    list.appendChild(fragment);
                    setupIcons(list);
                    setupImageFallbacks(list);
                    setupScribbleAvatars(list);
                    bindActionMenus(list);
                    bindActionToggles(list);
                    setupReportModal(list);
                    if (list.dataset.threaded === 'true') {
                        syncThreadedCommentState(list, list.dataset.projectSlug ?? '');
                    }
                    applyCommentSort(list, list.dataset.commentSort ?? 'new');
                }
                if (typeof data.total === 'number') {
                    list.dataset.commentsTotal = String(data.total);
                }
                const nextOffset = Number.isFinite(Number(data.next_offset))
                    ? Number(data.next_offset)
                    : offset + items.length;
                list.dataset.commentsOffset = String(nextOffset);
                document.dispatchEvent(new CustomEvent('comments:updated'));
            } catch {
                // ignore
            } finally {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.removeAttribute('aria-busy');
            }
        });
    });
};

const buildCommentNode = (entry: CommentEntry, avatarUrl: string, label: string, options: CommentNodeOptions = {}) => {
    const threaded = options.threaded ?? false;
    const variant = options.variant ?? 'thread';
    const wrapper = document.createElement('div');
    if (threaded) {
        wrapper.className = `comment ${variant === 'reply' ? 'comment--reply' : 'comment--threaded'}`;
        if (variant !== 'reply') {
            wrapper.dataset.commentItem = 'true';
        }
    } else {
        wrapper.className = 'comment';
        wrapper.dataset.commentItem = 'true';
    }
    wrapper.dataset.commentCreated = String(entry.createdAt);
    wrapper.dataset.commentUseful = String(entry.useful ?? 0);
    wrapper.dataset.commentAuthor = entry.author;
    wrapper.dataset.commentPreview = entry.preview ?? entry.text;
    if (entry.id !== undefined) {
        wrapper.dataset.commentId = String(entry.id);
    }
    const anchor = options.anchor ?? (entry.id !== undefined ? `comment-${entry.id}` : '');
    if (anchor) {
        wrapper.id = anchor;
        wrapper.dataset.commentAnchor = anchor;
    }

    const meta = document.createElement('div');
    meta.className = 'comment-meta';

    const avatar = document.createElement('img');
    avatar.className = 'avatar';
    avatar.src = avatarUrl;
    avatar.alt = entry.author;
    applyScribbleAvatar(avatar, entry.author);

    const author = document.createElement(entry.authorSlug ? 'a' : 'span');
    author.className = 'post-author';
    author.textContent = entry.author;
    if (entry.authorSlug) {
        author.setAttribute('href', `${appUrl}/profile/${entry.authorSlug}`);
    }

    const time = document.createElement('span');
    time.className = 'comment-time';
    time.textContent = label;

    meta.appendChild(avatar);
    meta.appendChild(author);
    const roleKey = getRoleKey(entry.role);
    const roleLabel =
        entry.roleLabel ?? (entry.role ? entry.role.charAt(0).toUpperCase() + entry.role.slice(1) : '');
    if (roleLabel) {
        const badge = document.createElement('span');
        badge.className = `badge badge--${roleKey}`;
        badge.textContent = roleLabel;
        meta.appendChild(badge);
    }
    if (entry.isAuthor) {
        const badge = document.createElement('span');
        badge.className = 'badge badge--author';
        badge.textContent = t('author_badge', 'Author');
        meta.appendChild(badge);
    }
    meta.appendChild(time);

    if (entry.section) {
        const chip = document.createElement('span');
        chip.className = 'chip chip--comment';
        chip.textContent = entry.section;
        meta.appendChild(chip);
    }

    const body = document.createElement('p');
    body.className = 'comment-body';
    body.textContent = entry.text;

    if (threaded) {
        const buildVoteButton = (labelText: string, iconName: string, direction: 'up' | 'down') => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'vote-btn';
            button.dataset.commentVote = direction;
            button.setAttribute('aria-label', labelText);
            button.setAttribute('aria-pressed', 'false');
            const icon = document.createElement('i');
            icon.dataset.lucide = iconName;
            icon.className = 'icon';
            button.appendChild(icon);
            return button;
        };

        const vote = document.createElement('div');
        vote.className = 'comment-vote';
        vote.appendChild(buildVoteButton(t('comment_upvote', 'Upvote'), 'arrow-up', 'up'));
        const count = document.createElement('span');
        count.className = 'vote-count';
        count.textContent = String(entry.useful ?? 0);
        vote.appendChild(count);
        vote.appendChild(buildVoteButton(t('comment_downvote', 'Downvote'), 'arrow-down', 'down'));

        const content = document.createElement('div');
        content.className = 'comment-content';
        content.appendChild(meta);
        content.appendChild(body);

        const actions = document.createElement('div');
        actions.className = 'comment-actions';
        const buildAction = (label: string, iconName: string, dataKey: string) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'comment-action';
            button.dataset[dataKey] = 'true';
            button.setAttribute('aria-label', label);
            const icon = document.createElement('i');
            icon.dataset.lucide = iconName;
            icon.className = 'icon';
            button.appendChild(icon);
            return button;
        };

        actions.appendChild(buildAction(t('comment_reply', 'Reply'), 'corner-up-left', 'commentReply'));
        actions.appendChild(buildAction(t('comment_share', 'Share'), 'share-2', 'commentShare'));
        content.appendChild(actions);

        wrapper.appendChild(vote);
        wrapper.appendChild(content);
        return wrapper;
    }

    wrapper.appendChild(meta);
    wrapper.appendChild(body);
    return wrapper;
};

export const setupCommentForms = () => {
    const forms = document.querySelectorAll<HTMLFormElement>('[data-comment-form]');
    if (!forms.length) {
        return;
    }

    forms.forEach((form) => {
        const button = form.querySelector<HTMLButtonElement>('[data-comment-submit]');
        const textarea = form.querySelector<HTMLTextAreaElement>('textarea');
        const slug = form.dataset.projectSlug ?? '';
        const currentSlug = form.dataset.currentSlug ?? '';
        const postAuthorSlug = form.dataset.postAuthorSlug ?? '';
        const list = document.querySelector<HTMLElement>(`[data-comment-list][data-project-slug="${slug}"]`);
        const empty = list?.querySelector<HTMLElement>('[data-comment-empty]');
        const sectionInput = form.querySelector<HTMLInputElement>('[data-comment-section]');
        const avatarUrl = (document.body.dataset.appUrl ?? '').replace(/\/$/, '') + '/images/avatar-default.svg';
        const justNowLabel = t('comment_just_now', 'just now');
        const endpoint = `${appUrl}/projects/${slug}/comments`;
        const threaded = list?.dataset.threaded === 'true';
        const replyPreview = form.querySelector<HTMLElement>('[data-reply-preview]');
        const replyAuthor = form.querySelector<HTMLElement>('[data-reply-author]');
        const replyText = form.querySelector<HTMLElement>('[data-reply-text]');
        const replyCancel = form.querySelector<HTMLButtonElement>('[data-reply-cancel]');
        let replyTargetEl: HTMLElement | null = null;

        if (!button || !textarea || !slug || !list) {
            return;
        }

        if (empty && list.querySelector('[data-comment-item]')) {
            empty.remove();
        }

        const applySort = (mode: string) => {
            applyCommentSort(list, mode);
        };

        const getActiveSort = () => list.dataset.commentSort ?? 'new';

        const clearReply = () => {
            replyTargetEl = null;
            delete form.dataset.replyId;
            delete form.dataset.replyAnchor;
            if (replyPreview) {
                replyPreview.hidden = true;
            }
        };

        const isBound = form.dataset.commentFormBound === '1';
        if (threaded) {
            syncThreadedCommentState(list, slug);
        }
        if (isBound) {
            return;
        }
        form.dataset.commentFormBound = '1';

        replyCancel?.addEventListener('click', () => {
            clearReply();
        });

        const resolveThreadRoot = (commentEl: HTMLElement) =>
            commentEl.closest<HTMLElement>('.comment--threaded') ?? commentEl;

        if (threaded) {
            list.addEventListener('click', (event) => {
                const target = event.target as HTMLElement;
                const voteButton = target.closest<HTMLButtonElement>('[data-comment-vote]');
                if (voteButton) {
                    const commentEl = voteButton.closest<HTMLElement>('.comment');
                    if (!commentEl) {
                        return;
                    }
                    const anchor = commentEl.dataset.commentAnchor ?? commentEl.id ?? '';
                    if (!anchor) {
                        return;
                    }
                    const direction = voteButton.dataset.commentVote === 'down' ? -1 : 1;
                    const votes = getCommentVoteMap(slug);
                    const current = normalizeCommentVote(votes[anchor]);
                    const nextVote = current === direction ? 0 : direction;
                    if (nextVote === 0) {
                        delete votes[anchor];
                    } else {
                        votes[anchor] = nextVote;
                    }
                    setCommentVoteMap(slug, votes);
                    applyCommentVoteState(commentEl, nextVote);
                    if (getActiveSort() === 'best') {
                        applySort('best');
                    }
                    return;
                }
                const replyButton = target.closest<HTMLButtonElement>('[data-comment-reply]');
                if (replyButton) {
                    const commentEl = replyButton.closest<HTMLElement>('.comment');
                    if (!commentEl) {
                        return;
                    }
                    const root = resolveThreadRoot(commentEl);
                    replyTargetEl = root;
                    const rootId = root.dataset.commentId ?? '';
                    if (rootId) {
                        form.dataset.replyId = rootId;
                    } else {
                        delete form.dataset.replyId;
                    }
                    form.dataset.replyAnchor = root.dataset.commentAnchor ?? root.id ?? '';
                    if (replyPreview) {
                        replyPreview.hidden = false;
                    }
                    if (replyAuthor) {
                        replyAuthor.textContent = commentEl.dataset.commentAuthor ?? '';
                    }
                    if (replyText) {
                        replyText.textContent = commentEl.dataset.commentPreview ?? '';
                    }
                    textarea.focus();
                    return;
                }

                const shareButton = target.closest<HTMLButtonElement>('[data-comment-share]');
                if (shareButton) {
                    const commentEl = shareButton.closest<HTMLElement>('.comment');
                    if (!commentEl) {
                        return;
                    }
                    const anchor = commentEl.dataset.commentAnchor ?? commentEl.id ?? '';
                    const base = window.location.href.split('#')[0];
                    const url = anchor ? `${base}#${anchor}` : base;
                    const fallbackCopy = () => {
                        const holder = document.createElement('input');
                        holder.value = url;
                        document.body.appendChild(holder);
                        holder.select();
                        try {
                            document.execCommand('copy');
                            toast.show(t('share_copied', 'Link copied.'));
                        } catch {
                            toast.show(t('share_failed', 'Unable to share.'));
                        } finally {
                            holder.remove();
                        }
                    };
                    if (navigator.clipboard?.writeText) {
                        navigator.clipboard
                            .writeText(url)
                            .then(() => toast.show(t('share_copied', 'Link copied.')))
                            .catch(() => fallbackCopy());
                    } else {
                        fallbackCopy();
                    }
                    return;
                }
            });
        }

        button.addEventListener('click', async () => {
            const text = textarea.value.trim();
            const section = sectionInput?.value.trim() ?? '';
            if (!text) {
                return;
            }
            try {
                const parentValue = form.dataset.replyId ?? '';
                const parentId = parentValue && /^\d+$/.test(parentValue) ? Number(parentValue) : null;
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ body: text, section: section || null, parent_id: parentId }),
                });
                if (!response.ok) {
                    return;
                }
                const payload = (await response.json()) as {
                    id?: number;
                    author: string;
                    author_slug?: string;
                    role: string;
                    role_label: string;
                    time: string;
                    text: string;
                    section?: string | null;
                    created_at?: number | null;
                    parent_id?: number | null;
                };
                const previewText = (payload.text ?? text).slice(0, 140);
                const entry: CommentEntry = {
                    id: payload.id,
                    author: payload.author ?? 'Anonymous',
                    text: payload.text ?? text,
                    createdAt: payload.created_at ?? Date.now(),
                    useful: 0,
                    role: payload.role ?? 'user',
                    roleLabel: payload.role_label ?? '',
                    authorSlug: payload.author_slug ?? (currentSlug || undefined),
                    section: payload.section ?? (section || undefined),
                    preview: previewText,
                    isAuthor: !!currentSlug && !!postAuthorSlug && currentSlug === postAuthorSlug,
                };
                const anchor = payload.id ? `comment-${payload.id}` : `comment-${slug}-${entry.createdAt}`;
                const isReply = threaded && replyTargetEl;
                const node = buildCommentNode(entry, avatarUrl, payload.time ?? justNowLabel, {
                    threaded,
                    variant: isReply ? 'reply' : 'thread',
                    anchor,
                });
                node.dataset.commentCreated = String(entry.createdAt);
                if (isReply && replyTargetEl) {
                    const replies =
                        replyTargetEl.querySelector<HTMLElement>('[data-comment-replies]') ??
                        (() => {
                            const container = document.createElement('div');
                            container.className = 'comment-replies';
                            container.dataset.commentReplies = 'true';
                            replyTargetEl?.querySelector('.comment-content')?.appendChild(container);
                            return container;
                        })();
                    replies.appendChild(node);
                } else {
                    list.appendChild(node);
                    applySort('new');
                    if (empty) {
                        empty.remove();
                    }
                    if (list.dataset.commentsTotal !== undefined || list.dataset.commentsOffset !== undefined) {
                        const currentCount = list.querySelectorAll<HTMLElement>('[data-comment-item]').length;
                        const offsetValue = Number(list.dataset.commentsOffset ?? currentCount);
                        const totalValue = Number(list.dataset.commentsTotal ?? offsetValue);
                        const nextOffset = Number.isFinite(offsetValue) ? offsetValue + 1 : currentCount;
                        const nextTotal = Number.isFinite(totalValue) ? totalValue + 1 : currentCount;
                        list.dataset.commentsOffset = String(nextOffset);
                        list.dataset.commentsTotal = String(nextTotal);
                        document.dispatchEvent(new CustomEvent('comments:updated'));
                    }
                }
                textarea.value = '';
                if (sectionInput) {
                    sectionInput.value = '';
                }
                if (threaded) {
                    setupIcons(node);
                    clearReply();
                    syncThreadedCommentState(list, slug);
                }
                toast.show(t('comment_sent', 'Comment sent (demo).'));
            } catch {
                // ignore
            }
        });
    });
};
