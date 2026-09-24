// Shared Formatters & Utilities for Finance Pages

export const formatRupiah = (num) => {
    const val = Number(num || 0);
    const hasDecimals = val % 1 !== 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(val);
};

export const formatNumberOnly = (num) => {
    const val = Number(num || 0);
    const hasDecimals = val % 1 !== 0;
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(val);
};
