// App currency is Myanmar Kyat (MMK). Kyat is used as whole numbers in
// practice (the pya subunit is effectively obsolete), so amounts are shown
// with no decimals and a "Ks" suffix. Change CURRENCY here to relabel
// everywhere.
export const CURRENCY = 'Ks';

export function money(n) {
    const formatted = Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
    return `${formatted} ${CURRENCY}`;
}
