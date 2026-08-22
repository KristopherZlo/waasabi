const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const focusableElements = (modal: HTMLElement) =>
    Array.from(modal.querySelectorAll<HTMLElement>(focusableSelector)).filter(
        (element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true',
    );

export const rememberFocus = () =>
    document.activeElement instanceof HTMLElement ? document.activeElement : null;

export const focusModal = (modal: HTMLElement, preferred?: HTMLElement | null) => {
    requestAnimationFrame(() => (preferred ?? focusableElements(modal)[0] ?? modal).focus());
};

export const restoreFocus = (element: HTMLElement | null) => {
    if (element?.isConnected) {
        requestAnimationFrame(() => element.focus());
    }
};

export const trapModalFocus = (modal: HTMLElement, event: KeyboardEvent) => {
    if (event.key !== 'Tab') {
        return;
    }
    const elements = focusableElements(modal);
    if (!elements.length) {
        event.preventDefault();
        modal.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
