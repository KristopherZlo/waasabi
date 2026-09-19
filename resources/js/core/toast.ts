export const toast = (() => {
    const element = document.querySelector<HTMLElement>('[data-toast]');
    let timeout: number | undefined;
    let actionActive = false;

    const show = (message: string, options: {actionLabel?: string; onAction?: () => void; duration?: number} = {}) => {
        if (!element || (actionActive && !options.actionLabel)) {
            return;
        }
        actionActive = Boolean(options.actionLabel && options.onAction);
        element.replaceChildren();
        const text = document.createElement('span');
        text.textContent = message;
        element.appendChild(text);
        if (options.actionLabel && options.onAction) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = options.actionLabel;
            button.addEventListener('click', () => {
                window.clearTimeout(timeout);
                actionActive = false;
                element.classList.remove('is-visible', 'has-action');
                options.onAction?.();
            }, {once: true});
            element.appendChild(button);
        }
        element.classList.toggle('has-action', actionActive);
        element.classList.add('is-visible');
        window.clearTimeout(timeout);
        timeout = window.setTimeout(() => {
            actionActive = false;
            element.classList.remove('is-visible');
            element.classList.remove('has-action');
        }, options.duration ?? 4200);
    };

    return { show };
})();
