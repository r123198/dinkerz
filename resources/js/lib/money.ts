const formatters = new Map<string, Intl.NumberFormat>();

export function formatMoney(amount: number, currency = 'PHP'): string {
    if (!formatters.has(currency)) {
        formatters.set(
            currency,
            new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency,
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }),
        );
    }

    return formatters.get(currency)!.format(amount);
}
