const dateFormat = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

export function formatInvestorDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : dateFormat.format(date);
}
