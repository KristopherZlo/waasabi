import { appUrl, csrfToken } from '../core/config';
import { t } from '../core/i18n';
import { toast } from '../core/toast';

export const submitReport = async (payload: {
    content_type: string;
    content_id?: string | null;
    content_url?: string | null;
    reason: string;
    details?: string | null;
}) => {
    if (!csrfToken) {
        return false;
    }
    try {
        const response = await fetch(`${appUrl}/reports`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });
        return response.ok;
    } catch {
        return false;
    }
};

export const setupReportModal = (root: ParentNode = document) => {
    const modal = document.querySelector<HTMLElement>('[data-report-modal]');
    const form = modal?.querySelector<HTMLFormElement>('[data-report-form]') ?? null;
    const closeButton = modal?.querySelector<HTMLButtonElement>('[data-report-close]') ?? null;
    const cancelButton = modal?.querySelector<HTMLButtonElement>('[data-report-cancel]') ?? null;
    if (!modal || !form) {
        return;
    }

    const typeInput = form.querySelector<HTMLInputElement>('[data-report-type]');
    const idInput = form.querySelector<HTMLInputElement>('[data-report-id]');
    const urlInput = form.querySelector<HTMLInputElement>('[data-report-url]');
    const reasonInput = form.querySelector<HTMLSelectElement>('[data-report-reason]');
    const detailsInput = form.querySelector<HTMLTextAreaElement>('[data-report-details]');

    const close = () => {
        modal.hidden = true;
    };

    const open = (button: HTMLElement) => {
        modal.hidden = false;
        typeInput?.setAttribute('value', button.dataset.reportType ?? 'content');
        if (idInput) {
            idInput.value = button.dataset.reportId ?? '';
        }
        if (urlInput) {
            urlInput.value = button.dataset.reportUrl ?? window.location.href;
        }
        if (reasonInput) {
            reasonInput.value = button.dataset.reportReason ?? 'other';
        }
        if (detailsInput) {
            detailsInput.value = '';
        }
    };

    const bindOpenButtons = (scope: ParentNode) => {
        const buttons = Array.from(scope.querySelectorAll<HTMLElement>('[data-report-open]'));
        if (!buttons.length) {
            return;
        }
        buttons.forEach((button) => {
            if (button.dataset.reportBound === '1') {
                return;
            }
            button.dataset.reportBound = '1';
            button.addEventListener('click', () => open(button));
        });
    };

    bindOpenButtons(root);

    if (modal.dataset.reportModalBound !== '1') {
        modal.dataset.reportModalBound = '1';
        closeButton?.addEventListener('click', close);
        cancelButton?.addEventListener('click', close);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            content_type: typeInput?.value ?? 'content',
            content_id: idInput?.value ?? null,
            content_url: urlInput?.value ?? window.location.href,
            reason: reasonInput?.value ?? 'other',
            details: detailsInput?.value ?? null,
        };
        const ok = await submitReport(payload);
        if (ok) {
            toast.show(t('report_sent', 'Report sent.'));
            close();
        } else {
            toast.show(t('report_failed', 'Unable to send report.'));
        }
    });
};
