import { appUrl, csrfToken } from '../core/config';

export const setupFollowButtons = () => {
    const forms = Array.from(document.querySelectorAll<HTMLFormElement>('[data-follow-form]'));
    if (!forms.length) {
        return;
    }

    forms.forEach((form) => {
        if (form.dataset.followBound === '1') {
            return;
        }
        form.dataset.followBound = '1';
        const button = form.querySelector<HTMLButtonElement>('[data-follow-button]');
        if (!button) {
            return;
        }

        const followLabel = button.dataset.followLabel ?? button.textContent ?? '';
        const unfollowLabel = button.dataset.unfollowLabel ?? button.textContent ?? '';
        const followersCountEl = document.querySelector<HTMLElement>('[data-followers-count]');
        const followingCountEl = document.querySelector<HTMLElement>('[data-following-count]');

        const updateState = (isFollowing: boolean) => {
            form.dataset.following = isFollowing ? '1' : '0';
            button.textContent = isFollowing ? unfollowLabel : followLabel;
            button.classList.toggle('is-active', isFollowing);
        };

        updateState(form.dataset.following === '1');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const wasFollowing = form.dataset.following === '1';
            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(form),
                });

                if (response.status === 401) {
                    window.location.href = `${appUrl}/login`;
                    return;
                }

                if (!response.ok) {
                    form.submit();
                    return;
                }

                const data = (await response.json()) as {
                    is_following?: boolean;
                    followers_count?: number;
                    following_count?: number;
                };

                if (typeof data.is_following === 'boolean') {
                    updateState(data.is_following);
                } else {
                    updateState(!wasFollowing);
                }

                if (followersCountEl && typeof data.followers_count === 'number') {
                    followersCountEl.textContent = String(data.followers_count);
                }

                if (followingCountEl && typeof data.following_count === 'number') {
                    followingCountEl.textContent = String(data.following_count);
                }
            } catch {
                form.submit();
            } finally {
                button.disabled = false;
            }
        });
    });
};
