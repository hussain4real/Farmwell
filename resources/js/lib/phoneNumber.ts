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
