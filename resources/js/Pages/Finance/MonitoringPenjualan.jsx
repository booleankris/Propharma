import React from 'react';
import { formatRupiah } from './Components/Utils';

const firstColumns = [56, 250, 150, 126];
const shifts = ['morning', 'evening', 'otherShift'];

function section(code, label) {
    return { type: 'section', code, label };
}

function gap() {
    return { type: 'gap' };
}

function row(no, label, area, key, tone = '') {
    return { type: 'data', no, label, area, key, tone };
}

const reportRows = [
    section('I', 'PENJUALAN OBAT TOTAL'),
    row('1', 'RESEP TUNAI', 'sales', 'cashPrescription'),
    row('2', 'UPDS', 'sales', 'upds'),
    row('3', 'HV', 'sales', 'hv'),
    row('4', 'RETUR TUNAI', 'sales', 'returns'),
    row('', 'PENJUALAN TUNAI', 'sales', 'cashTotal', 'yellow'),
    row('1', 'RESEP KREDIT', 'sales', 'creditPrescription'),
    row('', 'PENJUALAN KREDIT', 'sales', 'creditTotal', 'yellow'),
    row('', 'TOTAL PENJUALAN OBAT', 'sales', 'total', 'green'),
    row('', 'TOTAL PENJUALAN ALL', 'sales', 'total', 'blue'),
    gap(),
    section('I', 'PENJUALAN ONLINE'),
    row('', 'WHATSAPP', 'sales', 'onlineWhatsappTotal', 'yellow'),
    row('1', 'NON RESEP', 'sales', 'onlineWhatsappNonPrescription'),
    row('2', 'RESEP', 'sales', 'onlineWhatsappPrescription'),
    row('', 'SHOPEE', 'sales', 'onlineShopee', 'yellow'),
    row('', 'TIKTOK', 'sales', 'onlineTiktok', 'yellow'),
    row('', 'GRABMART', 'sales', 'onlineGrabmart', 'yellow'),
    row('', 'DIGITAL / ONLINE LAINNYA', 'sales', 'onlineDigital', 'yellow'),
    row('', 'TOTAL PENJUALAN ONLINE', 'sales', 'onlineTotal', 'blue'),
    gap(),
    section('I', 'PENJUALAN OFFLINE'),
    row('', 'OFFLINE', 'sales', 'offlineTotal', 'yellow'),
    row('1', 'NON RESEP', 'sales', 'offlineNonPrescription'),
    row('2', 'RESEP', 'sales', 'offlinePrescription'),
    row('', 'TOTAL PENJUALAN OFFLINE', 'sales', 'offlineTotal', 'blue'),
    row('', 'RETUR', 'sales', 'offlineReturns'),
    gap(),
    section('', 'TOTAL KUNJUNGAN (DIKOSONGKAN)'),
    row('1', 'NON RESEP', null, null),
    row('2', 'RESEP', null, null),
    row('', 'TRANSAKSI TUNAI', null, null, 'yellow'),
    row('', 'TOTAL KUNJUNGAN ONLINE', null, null),
    row('', 'TOTAL KUNJUNGAN OFFLINE', null, null),
    row('', 'TOTAL KUNJUNGAN ALL', null, null, 'blue'),
    gap(),
    section('II', 'TRANSAKSI PENJUALAN (LEMBAR)'),
    row('1', 'RESEP TUNAI', 'receipts', 'cashPrescription'),
    row('2', 'UPDS', 'receipts', 'upds'),
    row('3', 'HV', 'receipts', 'hv'),
    row('', 'TRANSAKSI TUNAI', 'receipts', 'cashTotal', 'yellow'),
    row('1', 'RESEP KREDIT', 'receipts', 'creditPrescription'),
    row('', 'TRANSAKSI KREDIT', 'receipts', 'creditTotal', 'yellow'),
    row('', 'TOTAL TRANSAKSI ALL', 'receipts', 'total', 'green'),
    row('', 'TOTAL TRANSAKSI ALL', 'receipts', 'total', 'blue'),
    gap(),
    section('II', 'TRANSAKSI PENJUALAN (LEMBAR) ONLINE'),
    row('', 'WHATSAPP', 'receipts', 'onlineWhatsappTotal', 'yellow'),
    row('1', 'NON RESEP', 'receipts', 'onlineWhatsappNonPrescription'),
    row('2', 'RESEP', 'receipts', 'onlineWhatsappPrescription'),
    row('', 'SHOPEE', 'receipts', 'onlineShopee', 'yellow'),
    row('', 'TIKTOK', 'receipts', 'onlineTiktok', 'yellow'),
    row('', 'GRABMART', 'receipts', 'onlineGrabmart', 'yellow'),
    row('', 'DIGITAL / ONLINE LAINNYA', 'receipts', 'onlineDigital', 'yellow'),
    row('', 'TOTAL TRANSAKSI ONLINE', 'receipts', 'onlineTotal', 'blue'),
    gap(),
    section('II', 'TRANSAKSI PENJUALAN (LEMBAR) OFFLINE'),
    row('', 'OFFLINE', 'receipts', 'offlineTotal', 'yellow'),
    row('1', 'NON RESEP', 'receipts', 'offlineNonPrescription'),
    row('2', 'RESEP', 'receipts', 'offlinePrescription'),
    row('', 'TOTAL TRANSAKSI OFFLINE', 'receipts', 'offlineTotal', 'blue'),
    gap(),
    section('III', 'TOTAL TRANSAKSI ONLINE'),
    row('', 'WHATSAPP', 'receipts', 'onlineWhatsappTotal', 'yellow'),
    row('1', 'NON RESEP', 'receipts', 'onlineWhatsappNonPrescription'),
    row('2', 'RESEP', 'receipts', 'onlineWhatsappPrescription'),
    row('', 'SHOPEE', 'receipts', 'onlineShopee', 'yellow'),
    row('', 'TIKTOK', 'receipts', 'onlineTiktok', 'yellow'),
    row('', 'GRABMART', 'receipts', 'onlineGrabmart', 'yellow'),
    row('', 'DIGITAL / ONLINE LAINNYA', 'receipts', 'onlineDigital', 'yellow'),
    row('', 'TOTAL TRANSAKSI ONLINE', 'receipts', 'onlineTotal', 'blue'),
    gap(),
    section('III', 'TOTAL TRANSAKSI OFFLINE'),
    row('', 'OFFLINE', 'receipts', 'offlineTotal', 'yellow'),
    row('1', 'NON RESEP', 'receipts', 'offlineNonPrescription'),
    row('2', 'RESEP', 'receipts', 'offlinePrescription'),
    row('', 'TOTAL TRANSAKSI OFFLINE', 'receipts', 'offlineTotal', 'blue'),
    gap(),
    section('IV', 'JUMLAH ITEM OBAT (R/)'),
    row('1', 'RESEP TUNAI', 'items', 'cashPrescription'),
    row('2', 'UPDS', 'items', 'upds'),
    row('3', 'HV', 'items', 'hv'),
    row('', 'JUMLAH ITEM TUNAI', 'items', 'cashTotal', 'yellow'),
    row('1', 'RESEP KREDIT', 'items', 'creditPrescription'),
    row('', 'JUMLAH ITEM KREDIT', 'items', 'creditTotal', 'yellow'),
    row('', 'TOTAL JUMLAH ITEM OBAT', 'items', 'total', 'green'),
    row('', 'TOTAL JUMLAH ITEM (R/) ALL', 'items', 'total', 'blue'),
    gap(),
    section('IV', 'JUMLAH ITEM ONLINE'),
    row('', 'WHATSAPP', 'items', 'onlineWhatsappTotal', 'yellow'),
    row('1', 'NON RESEP', 'items', 'onlineWhatsappNonPrescription'),
    row('2', 'RESEP', 'items', 'onlineWhatsappPrescription'),
    row('', 'SHOPEE', 'items', 'onlineShopee', 'yellow'),
    row('', 'TIKTOK', 'items', 'onlineTiktok', 'yellow'),
    row('', 'GRABMART', 'items', 'onlineGrabmart', 'yellow'),
    row('', 'DIGITAL / ONLINE LAINNYA', 'items', 'onlineDigital', 'yellow'),
    row('', 'TOTAL JUMLAH ITEM ONLINE', 'items', 'onlineTotal', 'blue'),
    gap(),
    section('IV', 'TOTAL ITEM OFFLINE'),
    row('', 'OFFLINE', 'items', 'offlineTotal', 'yellow'),
    row('1', 'NON RESEP', 'items', 'offlineNonPrescription'),
    row('2', 'RESEP', 'items', 'offlinePrescription'),
    row('', 'TOTAL JUMLAH ITEM OFFLINE', 'items', 'offlineTotal', 'blue'),
];

function valueFor(day, shift, row) {
    if (!row.area || !row.key) return null;
    const value = day?.[shift]?.[row.area]?.[row.key];
    return Number.isFinite(Number(value)) ? Number(value) : 0;
}

function formatCell(value, money) {
    if (value === null || value === undefined) return '';
    if (value === 0) return '–';
    return money ? formatRupiah(value) : Number(value).toLocaleString('id-ID');
}

function toneClass(tone) {
    if (tone === 'yellow') return 'bg-yellow-300 font-semibold';
    if (tone === 'green') return 'bg-lime-500 font-bold';
    if (tone === 'blue') return 'bg-blue-200 font-bold';
    return 'bg-white';
}

function MatrixRow({ row: item, days, tableWidth }) {
    if (item.type === 'gap') return <tr aria-hidden="true" className="h-3"><td colSpan={tableWidth} className="border-0 bg-white" /></tr>;
    if (item.type === 'section') {
        return <tr>
            <th className="sticky left-0 z-20 border border-slate-600 bg-slate-300 px-2 py-1 text-left">{item.code}</th>
            <th className="sticky z-20 border border-slate-600 bg-slate-300 px-2 py-1 text-left" style={{ left: firstColumns[0] }}>{item.label}</th>
            <td className="sticky z-20 border border-slate-600 bg-slate-300" style={{ left: firstColumns[0] + firstColumns[1] }} />
            <td className="sticky z-20 border border-slate-600 bg-slate-300" style={{ left: firstColumns[0] + firstColumns[1] + firstColumns[2] }} />
            <th colSpan={tableWidth - 4} className="border border-slate-600 bg-slate-300 px-2 py-1" />
        </tr>;
    }

    const blank = !item.area || !item.key;
    const morning = blank ? null : days.reduce((sum, day) => sum + (valueFor(day, 'morning', item) || 0), 0);
    const evening = blank ? null : days.reduce((sum, day) => sum + (valueFor(day, 'evening', item) || 0), 0);
    const other = blank ? null : days.reduce((sum, day) => sum + (valueFor(day, 'otherShift', item) || 0), 0);
    const isMoney = item.area === 'sales';
    const tone = toneClass(item.tone);

    return <tr className={tone}>
        <td className={`sticky left-0 z-[1] border border-slate-400 px-2 py-1 text-center ${tone}`}>{item.no}</td>
        <td className={`sticky z-[1] border border-slate-400 px-2 py-1 ${tone}`} style={{ left: firstColumns[0] }}>{item.label}</td>
        <td className={`sticky z-[1] border border-slate-400 px-2 py-1 text-right ${tone}`} style={{ left: firstColumns[0] + firstColumns[1] }} />
        <td className={`sticky z-[1] border border-slate-400 px-2 py-1 text-right ${tone}`} style={{ left: firstColumns[0] + firstColumns[1] + firstColumns[2] }} />
        {days.flatMap((day) => <React.Fragment key={`${day.date}-${item.label}`}>
            {['morning', 'evening'].map((shift) => <td key={`${day.date}-${shift}`} className={`whitespace-nowrap border border-slate-300 px-2 py-1 text-right ${item.tone ? '' : 'bg-blue-50'}`}>
                {formatCell(valueFor(day, shift, item), isMoney)}
            </td>)}
            <td className="border border-slate-300 bg-white px-2 py-1" />
        </React.Fragment>)}
        <td className="whitespace-nowrap border border-slate-400 bg-blue-200 px-2 py-1 text-right font-semibold">{formatCell(morning, isMoney)}</td>
        <td className="whitespace-nowrap border border-slate-400 bg-blue-200 px-2 py-1 text-right font-semibold">{formatCell(evening, isMoney)}</td>
        <td className="whitespace-nowrap border border-slate-500 bg-orange-100 px-2 py-1 text-right font-bold">{formatCell(blank ? null : morning + evening + other, isMoney)}</td>
        <td className="border border-slate-500 bg-orange-100 px-2 py-1" />
    </tr>;
}

export default function MonitoringPenjualan({ days = [] }) {
    const width = 2 + 2 + days.length * 3 + 4;
    const firstDate = days[0]?.date;
    const lastDate = days[days.length - 1]?.date;
    const period = firstDate && lastDate
        ? `${new Date(`${firstDate}T00:00:00`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}${firstDate.slice(0, 7) === lastDate.slice(0, 7) ? '' : ` – ${new Date(`${lastDate}T00:00:00`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}`}`
        : 'Periode terpilih';

    return <section className="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <div className="border-b-2 border-black px-4 py-3">
            <h2 className="text-lg font-bold uppercase tracking-wide text-slate-900">Monitoring Target Penjualan</h2>
            <p className="mt-1 text-sm text-slate-600">{period} · Pagi menggabungkan shift pagi dan siang · Target bulanan/harian dikosongkan · Total kunjungan dikosongkan</p>
        </div>
        <div className="overflow-auto">
            <table className="w-max min-w-full border-collapse text-xs tabular-nums">
                <colgroup>
                    <col style={{ width: firstColumns[0] }} />
                    <col style={{ width: firstColumns[1] }} />
                    <col style={{ width: firstColumns[2] }} />
                    <col style={{ width: firstColumns[3] }} />
                    {Array.from({ length: days.length * 3 + 4 }, (_, index) => <col key={index} style={{ width: 108 }} />)}
                </colgroup>
                <thead className="sticky top-0 z-30 text-center font-bold uppercase text-slate-900">
                    <tr className="bg-slate-300">
                        <th rowSpan="2" className="sticky left-0 z-40 border-2 border-black bg-slate-300 px-2 py-2">No.</th>
                        <th rowSpan="2" className="sticky z-40 border-2 border-black bg-slate-300 px-2 py-2" style={{ left: firstColumns[0] }}>Uraian</th>
                        <th rowSpan="2" className="sticky z-40 border-2 border-black bg-emerald-100 px-2 py-2" style={{ left: firstColumns[0] + firstColumns[1] }}>AP per bulan<br /><span className="font-normal">Rp</span></th>
                        <th rowSpan="2" className="sticky z-40 border-2 border-black bg-slate-300 px-2 py-2" style={{ left: firstColumns[0] + firstColumns[1] + firstColumns[2] }}>Target<br />harian</th>
                        {days.map((day) => <th key={day.date} colSpan="3" className="border-2 border-black bg-white px-2 py-2">{Number(day.date.slice(8, 10))}</th>)}
                        <th colSpan="2" className="border-2 border-black bg-sky-200 px-2 py-2">Realisasi s/d</th>
                        <th colSpan="2" className="border-2 border-black bg-orange-100 px-2 py-2">Realisasi</th>
                    </tr>
                    <tr className="bg-white">
                        {days.flatMap((day) => ['Pagi', 'Malam', '%'].map((label) => <th key={`${day.date}-${label}`} className="border-2 border-black px-2 py-1">{label}</th>))}
                        <th className="border-2 border-black bg-sky-200 px-2 py-1">Pagi</th>
                        <th className="border-2 border-black bg-sky-200 px-2 py-1">Malam</th>
                        <th className="border-2 border-black bg-orange-100 px-2 py-1">Total</th>
                        <th className="border-2 border-black bg-orange-100 px-2 py-1">% ase</th>
                    </tr>
                </thead>
                <tbody>
                    {reportRows.map((item, index) => <MatrixRow key={`${item.type}-${item.label}-${index}`} row={item} days={days} tableWidth={width} />)}
                </tbody>
            </table>
        </div>
        <div className="border-t border-slate-300 bg-slate-50 px-4 py-2 text-[11px] text-slate-600">
            Nilai menggunakan final_price per item (cadangan total_price). Resep/non-resep ditentukan dari tipe tiap item di medicine_cart; satu struk hanya dihitung sekali mengikuti klasifikasi LIPH.
        </div>
    </section>;
}
