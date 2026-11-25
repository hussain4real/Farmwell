export const OFFLINE_LOG_QUEUE_KEY = 'farmwell.offline.production_logs.v1';
export const OFFLINE_QUEUE_EVENT = 'farmwell:offline-queue-changed';

export type OfflineLogPayload = Record<
    string,
    string | number | boolean | null
>;

export type QueuedProductionLog = {
    id: string;
    url: string;
    domain: string;
    label: string;
    payload: OfflineLogPayload;
    queuedAt: string;
    lastError?: string | null;
};

function hasStorage(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.localStorage !== 'undefined'
    );
}

function notifyQueueChanged(): void {
    if (
        typeof window !== 'undefined' &&
        typeof window.dispatchEvent === 'function'
    ) {
        window.dispatchEvent(new Event(OFFLINE_QUEUE_EVENT));
    }
}

function persistQueue(items: QueuedProductionLog[]): void {
    if (!hasStorage()) {
        return;
    }

    window.localStorage.setItem(OFFLINE_LOG_QUEUE_KEY, JSON.stringify(items));
    notifyQueueChanged();
}

export function getOfflineLogs(): QueuedProductionLog[] {
    if (!hasStorage()) {
        return [];
    }

    const raw = window.localStorage.getItem(OFFLINE_LOG_QUEUE_KEY);

    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? (parsed as QueuedProductionLog[]) : [];
    } catch {
        return [];
    }
}

export function enqueueOfflineLog(
    entry: Omit<QueuedProductionLog, 'id' | 'queuedAt'>,
): QueuedProductionLog {
    const item: QueuedProductionLog = {
        ...entry,
        id:
            typeof crypto !== 'undefined' &&
            typeof crypto.randomUUID === 'function'
                ? crypto.randomUUID()
                : `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        queuedAt: new Date().toISOString(),
        lastError: entry.lastError ?? null,
    };

    const items = getOfflineLogs();
    items.push(item);
    persistQueue(items);

    return item;
}

export function removeOfflineLog(id: string): void {
    const next = getOfflineLogs().filter((item) => item.id !== id);
    persistQueue(next);
}

export function clearOfflineLogs(): void {
    persistQueue([]);
}

export function readXsrfToken(cookieString?: string): string {
    const source =
        cookieString ??
        (typeof document !== 'undefined' ? document.cookie : '');

    if (!source) {
        return '';
    }

    for (const part of source.split(';')) {
        const [rawKey, ...rawVal] = part.trim().split('=');

        if (rawKey === 'XSRF-TOKEN') {
            return decodeURIComponent(rawVal.join('='));
        }
    }

    return '';
}

export type OfflineSyncResult = {
    synced: number;
    failed: number;
    remaining: number;
};

export async function syncOfflineLogs(
    fetchImpl: typeof fetch = fetch,
): Promise<OfflineSyncResult> {
    const queue = getOfflineLogs();

    if (queue.length === 0) {
        return { synced: 0, failed: 0, remaining: 0 };
    }

    const token = readXsrfToken();
    const remaining: QueuedProductionLog[] = [];
    let synced = 0;
    let failed = 0;

    for (let i = 0; i < queue.length; i++) {
        const entry = queue[i];

        try {
            const response = await fetchImpl(entry.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify(entry.payload),
            });

            if (response.ok) {
                synced++;
                continue;
            }

            let errorMessage = `HTTP ${response.status}`;

            try {
                const body = (await response.json()) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                const firstFieldError = body.errors
                    ? Object.values(body.errors).flat()[0]
                    : undefined;
                errorMessage = firstFieldError || body.message || errorMessage;
            } catch {
                // Keep HTTP status fallback message.
            }

            failed++;
            remaining.push({ ...entry, lastError: errorMessage });
        } catch {
            // Network still unreachable: keep current + all subsequent entries in order.
            remaining.push(...queue.slice(i));
            break;
        }
    }

    persistQueue(remaining);

    return { synced, failed, remaining: remaining.length };
}

export function registerServiceWorker(): void {
    if (
        typeof window === 'undefined' ||
        typeof navigator === 'undefined' ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Keep app functional if SW registration is blocked in private mode.
        });
    });
}
