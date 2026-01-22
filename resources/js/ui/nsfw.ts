export const setupNsfwReveal = (root: ParentNode = document) => {
    const buttons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-nsfw-reveal]'));
    if (!buttons.length) {
        return;
    }

    buttons.forEach((button) => {
        if (button.dataset.nsfwRevealBound === '1') {
            return;
        }
        button.dataset.nsfwRevealBound = '1';
        button.addEventListener('click', () => {
            const cover = button.closest<HTMLElement>('[data-nsfw-cover]');
            if (!cover) {
                return;
            }
            cover.classList.add('is-revealed');
            button.hidden = true;
        });
    });
};
