export function nationalNumberLength(prefix: string): number | undefined {
    return { '+234': 10, '+974': 8 }[prefix];
}

export function pastedNationalDigits(prefix: string, text = ''): string {
    const compact = text.replace(/[\s()-]/g, '');

    if (compact.startsWith('+') && !compact.startsWith(prefix)) {
        return '';
    }

    const digits = compact.startsWith(prefix)
        ? compact.slice(prefix.length)
        : compact;
    const national = prefix === '+234' ? digits.replace(/^0/, '') : digits;
    const length = nationalNumberLength(prefix);

    return /^[1-9][0-9]*$/.test(national) && length && national.length <= length
        ? national
        : '';
}

export function internationalPhoneNumber(
    prefix: string,
    input: string,
): string {
    const digits = input.replace(/[\s()-]/g, '');

    if (digits.startsWith(prefix)) {
        return digits;
    }

    const local = prefix === '+234' ? digits.replace(/^0/, '') : digits;

    return local ? prefix + local : '';
}
