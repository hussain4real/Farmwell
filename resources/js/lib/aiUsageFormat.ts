export function formatUsdFromMicrodollars(
    microdollars: number,
    options: { compact?: boolean } = {},
): string {
    const safeMicrodollars = Number.isFinite(microdollars) ? microdollars : 0;
    const usd = safeMicrodollars / 1_000_000;
    const absUsd = Math.abs(usd);

    if (absUsd === 0) {
        return '$0.00';
    }

    if (options.compact && absUsd >= 1000) {
        return `$${(usd / 1000).toFixed(1)}k`;
    }

    let decimals = 2;

    if (absUsd < 0.01) {
        decimals = 4;
    } else if (absUsd < 1) {
        decimals = 4;
    } else if (absUsd < 10 && Math.round(absUsd * 1000) % 10 !== 0) {
        decimals = 3;
    }

    const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(usd);

    return formatted;
}

export function formatTokenCount(tokens: number): string {
    const safe = Number.isFinite(tokens) ? Math.max(0, Math.round(tokens)) : 0;

    if (safe >= 1_000_000) {
        return `${(safe / 1_000_000).toFixed(2)}M`;
    }

    if (safe >= 10_000) {
        return `${(safe / 1_000).toFixed(1)}K`;
    }

    return new Intl.NumberFormat('en-US').format(safe);
}

export function formatDurationMs(durationMs: number | null): string {
    if (durationMs === null || !Number.isFinite(durationMs)) {
        return '—';
    }

    if (durationMs >= 1000) {
        return `${(durationMs / 1000).toFixed(2)}s`;
    }

    return `${Math.round(durationMs)}ms`;
}

export function formatRatePerMillion(rate: number | null): string {
    if (rate === null || !Number.isFinite(rate)) {
        return '—';
    }

    const decimals = rate < 1 ? 3 : 2;

    return `$${rate.toFixed(decimals)}/1M`;
}
