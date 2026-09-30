import React from 'react';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { formatRupiah } from './Utils';

// Both bars use the same zero baseline and scale; exact values remain visible.
export default function CashFlowChart({ incoming = 0, outgoing = 0, compact = false }) {
    const safeAmount = (value) => Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
    const masuk = safeAmount(incoming);
    const keluar = safeAmount(outgoing);
    const maximum = Math.max(masuk, keluar, 1);
    const total = masuk + keluar;
    return (
        <figure className={`finance-flow-chart ${compact ? 'finance-flow-compact' : ''}`} aria-label="Perbandingan nilai kas masuk dan kas keluar">
            {compact ? <figcaption>Seluruh rekening · transaksi tercatat</figcaption> : <figcaption>Perbandingan arus kas <span>Data transaksi tercatat</span></figcaption>}
            {[
                { label: 'Kas masuk', value: masuk, tone: 'in', Icon: ArrowDownLeft },
                { label: 'Kas keluar', value: keluar, tone: 'out', Icon: ArrowUpRight },
            ].map(({ label, value, tone, Icon }) => (
                <div className={`finance-flow-row finance-flow-${tone}`} key={label}>
                    <div className="finance-flow-label"><span><Icon size={16} />{label}</span><strong>{formatRupiah(value)}</strong></div>
                    <div className="finance-flow-track" aria-hidden="true"><div className="finance-flow-bar" style={{ width: `${value / maximum * 100}%` }} /></div>
                    <p>{total > 0 ? `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value / total * 100)}% dari total arus kas` : 'Belum ada arus kas tercatat'}</p>
                </div>
            ))}
        </figure>
    );
}
