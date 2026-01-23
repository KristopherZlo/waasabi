export const setupIconTooltips = (root: ParentNode = document) => {
    const elements = Array.from(root.querySelectorAll<HTMLElement>('button, a'));
    if (!elements.length) {
        return;
    }

    elements.forEach((element) => {
        if (element.hasAttribute('title')) {
            return;
        }
        if (element.hasAttribute('data-tooltip')) {
            return;
        }
        const label = element.getAttribute('aria-label') ?? '';
        if (label.trim() === '') {
            return;
        }
        const text = (element.textContent ?? '').trim();
        if (text !== '') {
            return;
        }
        element.setAttribute('title', label);
    });
};
