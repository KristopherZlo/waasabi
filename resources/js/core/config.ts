export const csrfToken =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export const appUrl = (document.body.dataset.appUrl ?? '').replace(/\/$/, '');
