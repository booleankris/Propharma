// Shared Formatters & Utilities for Finance Pages

export const formatRupiah = (num) => {
    const val = Number(num || 0);
    const isNegative = val < 0;
    const absVal = Math.abs(val);
    const hasDecimals = absVal % 1 !== 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(absVal);

    return isNegative ? `-Rp\u00A0${formatted}` : `Rp\u00A0${formatted}`;
};

export const formatNumberOnly = (num) => {
    const val = Number(num || 0);
    const isNegative = val < 0;
    const absVal = Math.abs(val);
    const hasDecimals = absVal % 1 !== 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(absVal);

    return isNegative ? `-${formatted}` : formatted;
};
