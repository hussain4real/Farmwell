export function formatCurrency(
    amount: string | null | undefined,
    currency: string,
): string {
    if (amount == null) {
        return '—';
    }

    const code = currency.trim().toUpperCase();
    const match = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(amount);

    if (!match || !/^[A-Z]{3}$/.test(code)) {
        return `${amount} ${code}`.trim();
    }

    const sign = match[1];
    const whole = match[2].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = (match[3] ?? '').padEnd(2, '0');
    const symbol = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        currencyDisplay: 'narrowSymbol',
    })
        .formatToParts(0)
        .find((part) => part.type === 'currency')?.value;

    return !symbol || symbol === code
        ? `${sign}${code} ${whole}.${fraction}`
        : `${sign}${symbol}${whole}.${fraction} ${code}`;
}
