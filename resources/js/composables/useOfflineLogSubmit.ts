import { toast } from 'vue-sonner';
import { enqueueOfflineLog } from '@/lib/offlineLogQueue';
import type { OfflineLogPayload } from '@/lib/offlineLogQueue';

export function formElementToOfflinePayload(
    form: HTMLFormElement,
): OfflineLogPayload {
    const formData = new FormData(form);
    const payload: OfflineLogPayload = {};

    formData.forEach((value, key) => {
        if (typeof value !== 'string') {
            return;
        }

        const cleanKey = key.replace(/\[\]$/, '');

        if (value === '') {
            return;
        }

        payload[cleanKey] = value;
    });

    return payload;
}

export function interceptOfflineFormSubmit(
    event: SubmitEvent,
    meta: { url: string; domain: string; label: string },
): boolean {
    if (typeof navigator === 'undefined' || navigator.onLine !== false) {
        return false;
    }

    const form = event.currentTarget;

    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    event.preventDefault();
    event.stopPropagation();

    const payload = formElementToOfflinePayload(form);

    enqueueOfflineLog({
        url: meta.url,
        domain: meta.domain,
        label: meta.label,
        payload,
    });

    form.reset();
    toast.success('Saved offline — will sync automatically when back online.');

    return true;
}
