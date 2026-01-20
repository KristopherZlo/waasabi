export const toast = (() => {
    const element = document.querySelector<HTMLElement>('[data-toast]');
    let timeout: number | undefined;

    const show = (message: string) => {
        if (!element) {
            return;
        }
        element.textContent = message;
        element.classList.add('is-visible');
        window.clearTimeout(timeout);
        timeout = window.setTimeout(() => {
            element.classList.remove('is-visible');
        }, 1800);
    };

    return { show };
})();
