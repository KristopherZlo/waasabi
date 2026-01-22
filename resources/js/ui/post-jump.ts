export const setupPostJumpButtons = (root: ParentNode = document) => {
    const buttons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-post-jump]'));
    if (!buttons.length) {
        return;
    }
    const isHidden = (element: HTMLElement) =>
        element.hidden || element.classList.contains('is-hidden') || element.style.display === 'none';

    buttons.forEach((button) => {
        if (button.dataset.postJumpBound === '1') {
            return;
        }
        button.dataset.postJumpBound = '1';
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const card =
                button.closest<HTMLElement>('[data-feed-card]') ?? button.closest<HTMLElement>('.post-card');
            if (!card) {
                return;
            }
            let next = card.nextElementSibling as HTMLElement | null;
            while (next) {
                if (next.matches('[data-feed-card]') && !isHidden(next)) {
                    break;
                }
                next = next.nextElementSibling as HTMLElement | null;
            }
            if (!next) {
                return;
            }
            const topbar = document.querySelector<HTMLElement>('.topbar');
            const topbarHeight = topbar?.getBoundingClientRect().height ?? 0;
            const targetTop = next.getBoundingClientRect().top + window.scrollY - topbarHeight - 12;
            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: 'smooth',
            });
        });
    });
};
