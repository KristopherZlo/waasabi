import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { setupIcons, applyScribbleAvatar } from '../core/media';
import { getRoleKey } from '../core/roles';
import { normalizeCommentVote } from '../core/votes';
import { toast } from '../core/toast';

type ReviewEntry = {
    author: string;
    role: string;
    roleLabel?: string;
    authorSlug?: string;
    isAuthor?: boolean;
    note?: string;
    improve: string;
    why: string;
    how: string;
    createdAt: number;
    timeLabel?: string;
    useful?: number;
    id?: number | string;
};

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

const getReviewVoteKey = (slug: string) => `reviewVotes:${slug}`;
const getReviewVoteMap = (slug: string) =>
    parseJson<Record<string, number>>(localStorage.getItem(getReviewVoteKey(slug)), {});
const setReviewVoteMap = (slug: string, map: Record<string, number>) => {
    localStorage.setItem(getReviewVoteKey(slug), JSON.stringify(map));
};

const applyReviewVoteState = (reviewEl: HTMLElement, voteValue: number) => {
    const countEl = reviewEl.querySelector<HTMLElement>('.vote-count');
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
    reviewEl.dataset.reviewUseful = String(nextCount);
    const upBtn = reviewEl.querySelector<HTMLButtonElement>('[data-review-vote="up"]');
    const downBtn = reviewEl.querySelector<HTMLButtonElement>('[data-review-vote="down"]');
    if (upBtn) {
        upBtn.classList.toggle('is-active', voteValue === 1);
        upBtn.setAttribute('aria-pressed', voteValue === 1 ? 'true' : 'false');
    }
    if (downBtn) {
        downBtn.classList.toggle('is-active', voteValue === -1);
        downBtn.setAttribute('aria-pressed', voteValue === -1 ? 'true' : 'false');
    }
};

const syncReviewVoteState = (list: HTMLElement, slug: string) => {
    if (!slug) {
        return;
    }
    const votes = getReviewVoteMap(slug);
    const reviewItems = Array.from(list.querySelectorAll<HTMLElement>('[data-review-item]'));
    reviewItems.forEach((reviewEl) => {
        const anchor = reviewEl.dataset.reviewAnchor ?? reviewEl.dataset.reviewId ?? '';
        if (!anchor) {
            return;
        }
        const voteValue = normalizeCommentVote(votes[anchor]);
        applyReviewVoteState(reviewEl, voteValue);
    });
};

const buildReviewNode = (entry: ReviewEntry, avatarUrl: string, labels: Record<string, string>) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'comment review-card';
    wrapper.dataset.reviewItem = 'true';
    wrapper.dataset.reviewCreated = String(entry.createdAt);
    const anchor = entry.id !== undefined ? `review-${entry.id}` : `review-${entry.createdAt}`;
    wrapper.dataset.reviewAnchor = anchor;
    if (entry.id !== undefined) {
        wrapper.dataset.reviewId = String(entry.id);
    }
    wrapper.dataset.reviewUseful = String(entry.useful ?? 0);
    wrapper.id = anchor;

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

    const roleKey = getRoleKey(entry.role);
    const role = document.createElement('span');
    role.className = `badge badge--${roleKey}`;
    const roleText = entry.roleLabel ?? (entry.role ? entry.role.charAt(0).toUpperCase() + entry.role.slice(1) : '');
    role.textContent = roleText;

    meta.appendChild(avatar);
    meta.appendChild(author);
    meta.appendChild(role);
    if (entry.isAuthor) {
        const authorBadge = document.createElement('span');
        authorBadge.className = 'badge badge--author';
        authorBadge.textContent = t('author_badge', 'Author');
        meta.appendChild(authorBadge);
    }
    if (entry.timeLabel) {
        const dot = document.createElement('span');
        dot.className = 'dot';
        dot.textContent = '•';
        const time = document.createElement('span');
        time.textContent = entry.timeLabel;
        meta.appendChild(dot);
        meta.appendChild(time);
    }
    if (entry.note) {
        const note = document.createElement('span');
        note.className = 'helper';
        note.textContent = entry.note;
        meta.appendChild(note);
    }

    const buildBlock = (label: string, text: string) => {
        const block = document.createElement('div');
        block.className = 'review-block';
        const title = document.createElement('div');
        title.className = 'review-label';
        title.textContent = label;
        const content = document.createElement('div');
        content.className = 'review-text';
        content.textContent = text;
        block.appendChild(title);
        block.appendChild(content);
        return block;
    };

    const buildVoteButton = (labelText: string, iconName: string, direction: 'up' | 'down') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vote-btn';
        button.dataset.reviewVote = direction;
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
    content.className = 'review-content';
    content.appendChild(meta);
    content.appendChild(buildBlock(labels.improve, entry.improve));
    content.appendChild(buildBlock(labels.why, entry.why));
    content.appendChild(buildBlock(labels.how, entry.how));

    wrapper.appendChild(vote);
    wrapper.appendChild(content);
    return wrapper;
};

export const setupReviewForms = () => {
    const forms = document.querySelectorAll<HTMLFormElement>('[data-review-form]');
    if (!forms.length) {
        return;
    }

    forms.forEach((form) => {
        if (form.dataset.reviewFormBound === '1') {
            return;
        }
        form.dataset.reviewFormBound = '1';
        const slug = form.dataset.projectSlug ?? '';
        const list = document.querySelector<HTMLElement>(`[data-review-list][data-project-slug="${slug}"]`);
        const empty = list?.querySelector<HTMLElement>('[data-review-empty]');
        const avatarUrl = (document.body.dataset.appUrl ?? '').replace(/\/$/, '') + '/images/avatar-default.svg';
        const roleKey = getRoleKey(form.dataset.currentRole);
        const authorSlug = form.dataset.currentSlug ?? '';
        const postAuthorSlug = form.dataset.postAuthorSlug ?? '';
        const timeLabel = t('comment_just_now', 'just now');
        const endpoint = `${appUrl}/projects/${slug}/reviews`;
        const labels = {
            improve: form.dataset.reviewLabelImprove ?? 'What to improve',
            why: form.dataset.reviewLabelWhy ?? 'Why',
            how: form.dataset.reviewLabelHow ?? 'How',
        };
        const button = form.querySelector<HTMLButtonElement>('[data-review-submit]');
        const improve = form.querySelector<HTMLTextAreaElement>('[data-review-field="improve"]');
        const why = form.querySelector<HTMLTextAreaElement>('[data-review-field="why"]');
        const how = form.querySelector<HTMLTextAreaElement>('[data-review-field="how"]');

        if (!slug || !list || !button || !improve || !why || !how) {
            return;
        }

        if (empty && list.querySelector('[data-review-item]')) {
            empty.remove();
        }

        button.addEventListener('click', async () => {
            const improveText = improve.value.trim();
            const whyText = why.value.trim();
            const howText = how.value.trim();
            if (!improveText || !whyText || !howText) {
                return;
            }
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ improve: improveText, why: whyText, how: howText }),
                });
                if (!response.ok) {
                    return;
                }
                const payload = (await response.json()) as {
                    author: string;
                    role: string;
                    role_label: string;
                    time: string;
                    improve: string;
                    why: string;
                    how: string;
                    created_at?: number | null;
                    id?: number;
                };
                const entry: ReviewEntry = {
                    author: payload.author ?? 'Anonymous',
                    role: payload.role ?? roleKey,
                    roleLabel: payload.role_label ?? '',
                    authorSlug: authorSlug || undefined,
                    isAuthor: !!authorSlug && !!postAuthorSlug && authorSlug === postAuthorSlug,
                    improve: payload.improve ?? improveText,
                    why: payload.why ?? whyText,
                    how: payload.how ?? howText,
                    createdAt: payload.created_at ?? Date.now(),
                    timeLabel: payload.time ?? timeLabel,
                    useful: 0,
                    id: payload.id,
                };
                const node = buildReviewNode(entry, avatarUrl, labels);
                node.dataset.reviewCreated = String(entry.createdAt);
                list.appendChild(node);
                setupIcons(node);
                syncReviewVoteState(list, slug);
                if (empty) {
                    empty.remove();
                }
                improve.value = '';
                why.value = '';
                how.value = '';
                toast.show(t('review_sent', 'Review sent (demo).'));
            } catch {
                // ignore
            }
        });
    });
};

export const setupReviewVotes = () => {
    const lists = Array.from(document.querySelectorAll<HTMLElement>('[data-review-list]'));
    if (!lists.length) {
        return;
    }

    lists.forEach((list) => {
        const slug = list.dataset.projectSlug ?? '';
        if (!slug) {
            return;
        }
        if (list.dataset.reviewVotesBound === '1') {
            return;
        }
        list.dataset.reviewVotesBound = '1';

        syncReviewVoteState(list, slug);

        list.addEventListener('click', (event) => {
            const target = event.target as HTMLElement;
            const voteButton = target.closest<HTMLButtonElement>('[data-review-vote]');
            if (!voteButton) {
                return;
            }
            const reviewEl = voteButton.closest<HTMLElement>('[data-review-item]');
            if (!reviewEl) {
                return;
            }
            const anchor = reviewEl.dataset.reviewAnchor ?? reviewEl.dataset.reviewId ?? '';
            if (!anchor) {
                return;
            }
            const direction = voteButton.dataset.reviewVote === 'down' ? -1 : 1;
            const votes = getReviewVoteMap(slug);
            const current = normalizeCommentVote(votes[anchor]);
            const nextVote = current === direction ? 0 : direction;
            if (nextVote === 0) {
                delete votes[anchor];
            } else {
                votes[anchor] = nextVote;
            }
            setReviewVoteMap(slug, votes);
            applyReviewVoteState(reviewEl, nextVote);
        });
    });
};
