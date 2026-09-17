import './external-links.css';

const setupExternalLinkWarning = () => {
    const body = document.body;
    if (body.dataset.externalWarningReady === '1') return;
    body.dataset.externalWarningReady = '1';

    const dialog = document.createElement('dialog');
    dialog.className = 'external-link-dialog';
    dialog.innerHTML = '<div class="external-link-dialog__body"><strong></strong><p></p><code></code><div><button type="button"></button><a data-external-confirmed></a></div></div>';
    const title = dialog.querySelector('strong')!;
    const text = dialog.querySelector('p')!;
    const destination = dialog.querySelector('code')!;
    const cancel = dialog.querySelector('button')!;
    const continueLink = dialog.querySelector('a')!;
    title.textContent = body.dataset.externalWarningTitle || 'You are leaving waasabi';
    text.textContent = body.dataset.externalWarningText || 'Check the address before you continue.';
    cancel.textContent = body.dataset.externalWarningCancel || 'Stay here';
    continueLink.textContent = body.dataset.externalWarningContinue || 'Continue';
    continueLink.className = 'external-link-dialog__continue';
    continueLink.rel = 'noopener noreferrer';
    cancel.addEventListener('click', () => dialog.close());
    continueLink.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
    document.body.append(dialog);

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0) return;
        const anchor = event.target instanceof Element ? event.target.closest<HTMLAnchorElement>('a[href]') : null;
        if (!anchor || anchor.dataset.externalConfirmed !== undefined || anchor.hasAttribute('download')) return;
        const url = new URL(anchor.href, window.location.href);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin === window.location.origin) return;
        event.preventDefault();
        destination.textContent = url.href;
        continueLink.href = url.href;
        continueLink.target = anchor.target === '_blank' ? '_blank' : '_self';
        dialog.showModal();
    }, true);
};

setupExternalLinkWarning();
