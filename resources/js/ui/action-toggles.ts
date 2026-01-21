import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { getReadLaterList, getUpvoteList, setReadLaterList, setUpvoteList } from '../core/storage';
import { toast } from '../core/toast';
import { renderReadLaterList } from './read-later';

let readLaterSyncDone = false;
const upvoteAnimationTimers = new WeakMap<HTMLButtonElement, number>();

const resolveCsrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') ?? csrfToken;

const playUpvoteAnimation = (button: HTMLButtonElement) => {
    if (button.dataset.action !== 'upvote') {
        return;
    }
    const existingTimer = upvoteAnimationTimers.get(button);
    if (existingTimer) {
        window.clearTimeout(existingTimer);
    }
    button.classList.remove('is-upvote-animating');
    // Restart the animation even on repeated upvotes.
    void button.offsetWidth;
    button.classList.add('is-upvote-animating');
    const timerId = window.setTimeout(() => {
        button.classList.remove('is-upvote-animating');
        upvoteAnimationTimers.delete(button);
    }, 950);
    upvoteAnimationTimers.set(button, timerId);
};

const updateSaveButton = (button: HTMLButtonElement, saved: boolean) => {
    button.classList.toggle('is-active', saved);
    button.dataset.saved = saved ? '1' : '0';
    const label = button.querySelector<HTMLElement>('.action-label');
    if (label) {
        label.textContent = saved ? t('saved', 'Saved') : t('save', 'Save');
    }
};

const updateUpvoteButton = (button: HTMLButtonElement, upvoted: boolean) => {
    button.classList.toggle('is-active', upvoted);
    button.dataset.upvoted = upvoted ? '1' : '0';
    const count = button.querySelector<HTMLElement>('.action-count');
    if (count) {
        const rawCount = Number(button.dataset.baseCount ?? count.textContent ?? 0);
        let baseCount = rawCount;
        if (button.dataset.baseCountReady !== '1') {
            baseCount = Math.max(0, rawCount - (upvoted ? 1 : 0));
            button.dataset.baseCount = String(baseCount);
            button.dataset.baseCountReady = '1';
        }
        count.textContent = String(baseCount + (upvoted ? 1 : 0));
    }
};

const applySavedState = (buttons: HTMLButtonElement[], savedList: string[]) => {
    buttons.forEach((button) => {
        if (button.dataset.action !== 'save') {
            return;
        }
        const slug = button.dataset.projectSlug ?? '';
        const saved = button.dataset.saved === '1' || (slug !== '' && savedList.includes(slug));
        updateSaveButton(button, saved);
    });
};

const applyUpvoteState = (buttons: HTMLButtonElement[], upvoteList: string[]) => {
    buttons.forEach((button) => {
        if (button.dataset.action !== 'upvote') {
            return;
        }
        const slug = button.dataset.projectSlug ?? '';
        const upvoted = button.dataset.upvoted === '1' || (slug !== '' && upvoteList.includes(slug));
        updateUpvoteButton(button, upvoted);
    });
};

const syncReadLaterWithServer = async (buttons: HTMLButtonElement[]): Promise<string[] | null> => {
    if (readLaterSyncDone) {
        return null;
    }
    if (document.body.dataset.authState !== '1') {
        return null;
    }

    const localList = getReadLaterList();
    readLaterSyncDone = true;

    try {
        const response = await fetch(`${appUrl}/read-later/list`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            readLaterSyncDone = false;
            return null;
        }
        const data = (await response.json()) as { items?: string[] };
        const remoteList = Array.isArray(data.items) ? data.items : [];
        const remoteSet = new Set(remoteList);
        const localOnly = localList.filter((slug) => !remoteSet.has(slug));

        let mergedList = remoteList;
        let pushedLocalToServer = false;

        if (localOnly.length) {
            const token = resolveCsrfToken();
            const merged = Array.from(new Set([...remoteList, ...localOnly]));
            if (token) {
                const syncResponse = await fetch(`${appUrl}/read-later/sync`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ items: merged }),
                });
                if (syncResponse.ok) {
                    const syncData = (await syncResponse.json()) as { items?: string[] };
                    const synced = Array.isArray(syncData.items) ? syncData.items : [];
                    if (synced.length) {
                        mergedList = synced;
                    } else {
                        mergedList = merged;
                    }
                    pushedLocalToServer = true;
                } else {
                    mergedList = merged;
                }
            } else {
                mergedList = merged;
            }
        } else if (!remoteList.length && localList.length) {
            mergedList = localList;
        }

        const finalList = Array.from(new Set(mergedList));
        if (!finalList.length && !localList.length) {
            return null;
        }
        if (localOnly.length && !pushedLocalToServer) {
            // Allow another attempt later if the batch sync failed.
            readLaterSyncDone = false;
        }
        setReadLaterList(finalList);
        applySavedState(buttons, finalList);
        renderReadLaterList({ refreshPage: pushedLocalToServer });
        return finalList;
    } catch {
        // ignore
    }
    return null;
};

export const bindActionToggles = (root: ParentNode = document) => {
    const buttons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-action]'));
    if (!buttons.length) {
        return;
    }
    const isAuthed = document.body.dataset.authState === '1';
    let savedList = getReadLaterList();
    let upvoteList = getUpvoteList();

    const requestAction = async (action: string, slug: string) => {
        const token = resolveCsrfToken();
        if (!token) {
            return null;
        }
        try {
            const response = await fetch(`${appUrl}/posts/${slug}/${action}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
            });
            if (response.status === 401) {
                window.location.href = `${appUrl}/login`;
                return null;
            }
            if (response.status === 419) {
                // Session or CSRF token is stale; refresh to recover.
                window.location.reload();
                return null;
            }
            if (!response.ok) {
                return null;
            }
            return (await response.json()) as { saved?: boolean; upvoted?: boolean; count?: number };
        } catch {
            return null;
        }
    };

    applySavedState(buttons, savedList);
    applyUpvoteState(buttons, upvoteList);

    void syncReadLaterWithServer(buttons).then((synced) => {
        if (synced) {
            savedList = synced;
        }
    });

    buttons.forEach((button) => {
        if (button.dataset.actionBound === '1') {
            return;
        }
        button.dataset.actionBound = '1';
        button.addEventListener('click', async () => {
            const currentAction = button.dataset.action ?? '';
            const slug = button.dataset.projectSlug ?? '';
            if (!slug) {
                return;
            }

            if (button.dataset.actionPending === '1') {
                return;
            }
            button.dataset.actionPending = '1';
            try {
                if (currentAction === 'save') {
                    if (button.dataset.saved === '1' && !savedList.includes(slug)) {
                        savedList = [...savedList, slug];
                    }
                    const previousSavedList = [...savedList];
                    const wasSaved = previousSavedList.includes(slug);
                    const nextSaved = !wasSaved;

                    savedList = nextSaved
                        ? savedList.includes(slug)
                            ? savedList
                            : [...savedList, slug]
                        : savedList.filter((item) => item !== slug);
                    setReadLaterList(savedList);
                    updateSaveButton(button, nextSaved);
                    renderReadLaterList();

                    if (!isAuthed) {
                        toast.show(
                            nextSaved
                                ? t('saved_to_read_later', 'Saved to Read later.')
                                : t('removed_from_read_later', 'Removed from Read later.'),
                        );
                        return;
                    }

                    const result = await requestAction('save', slug);
                    if (!result || typeof result.saved !== 'boolean') {
                        savedList = previousSavedList;
                        setReadLaterList(savedList);
                        updateSaveButton(button, wasSaved);
                        renderReadLaterList();
                        toast.show(t('save_failed', 'Unable to save right now.'));
                        return;
                    }

                    const serverSaved = result.saved;
                    savedList = serverSaved
                        ? savedList.includes(slug)
                            ? savedList
                            : [...savedList, slug]
                        : savedList.filter((item) => item !== slug);
                    setReadLaterList(savedList);
                    updateSaveButton(button, serverSaved);
                    toast.show(
                        serverSaved
                            ? t('saved_to_read_later', 'Saved to Read later.')
                            : t('removed_from_read_later', 'Removed from Read later.'),
                    );
                    if (document.body.dataset.page === 'read-later' && !serverSaved) {
                        const card = button.closest<HTMLElement>('[data-feed-card]');
                        card?.remove();
                    }
                    renderReadLaterList();
                    return;
                }

                if (currentAction === 'upvote') {
                    if (!isAuthed) {
                        window.location.href = `${appUrl}/login`;
                        return;
                    }
                    if (button.dataset.upvoted === '1' && !upvoteList.includes(slug)) {
                        upvoteList = [...upvoteList, slug];
                    }
                    const previousUpvoteList = [...upvoteList];
                    const wasUpvoted = previousUpvoteList.includes(slug);
                    const nextUpvoted = !wasUpvoted;

                    upvoteList = nextUpvoted
                        ? upvoteList.includes(slug)
                            ? upvoteList
                            : [...upvoteList, slug]
                        : upvoteList.filter((item) => item !== slug);
                    setUpvoteList(upvoteList);
                    updateUpvoteButton(button, nextUpvoted);
                    if (nextUpvoted) {
                        playUpvoteAnimation(button);
                    }

                    const result = await requestAction('upvote', slug);
                    if (!result || typeof result.upvoted !== 'boolean') {
                        upvoteList = previousUpvoteList;
                        setUpvoteList(upvoteList);
                        updateUpvoteButton(button, wasUpvoted);
                        toast.show(t('upvote_failed', 'Unable to upvote right now.'));
                        return;
                    }

                    const serverUpvoted = result.upvoted;
                    upvoteList = serverUpvoted
                        ? upvoteList.includes(slug)
                            ? upvoteList
                            : [...upvoteList, slug]
                        : upvoteList.filter((item) => item !== slug);
                    setUpvoteList(upvoteList);

                    if (typeof result.count === 'number') {
                        const baseCount = Math.max(0, result.count - (serverUpvoted ? 1 : 0));
                        button.dataset.baseCount = String(baseCount);
                        button.dataset.baseCountReady = '1';
                    }
                    updateUpvoteButton(button, serverUpvoted);
                    if (serverUpvoted && !nextUpvoted) {
                        playUpvoteAnimation(button);
                    }
                }
            } finally {
                button.dataset.actionPending = '0';
            }
        });
    });
};

export const setupActionToggles = () => {
    bindActionToggles();
};
