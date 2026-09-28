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

export const terbilang = (angka) => {
    const bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    const num = Math.floor(Math.abs(Number(angka) || 0));

    if (num === 0) return 'Nol Rupiah';

    const sebut = (n) => {
        if (n < 12) return bilangan[n];
        if (n < 20) return `${sebut(n - 10)} Belas`;
        if (n < 100) return `${sebut(Math.floor(n / 10))} Puluh ${sebut(n % 10)}`.trim();
        if (n < 200) return `Seratus ${sebut(n - 100)}`.trim();
        if (n < 1000) return `${sebut(Math.floor(n / 100))} Ratus ${sebut(n % 100)}`.trim();
        if (n < 2000) return `Seribu ${sebut(n - 1000)}`.trim();
        if (n < 1000000) return `${sebut(Math.floor(n / 1000))} Ribu ${sebut(n % 1000)}`.trim();
        if (n < 1000000000) return `${sebut(Math.floor(n / 1000000))} Juta ${sebut(n % 1000000)}`.trim();
        if (n < 1000000000000) return `${sebut(Math.floor(n / 1000000000))} Miliar ${sebut(n % 1000000000)}`.trim();
        return `${sebut(Math.floor(n / 1000000000000))} Triliun ${sebut(n % 1000000000000)}`.trim();
    };

    return `${sebut(num)} Rupiah`;
};

