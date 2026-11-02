import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download, SlidersHorizontal } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field, RecordErrors } from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import '../css/employees.css';
import '../css/applications.css';
import '../css/trainings.css';
import '../css/reports.css';

type Filters = { module: string; from: string; to: string; status?: string };
type Props = {
    auth: { user: HrUser };
    errors?: Record<string, string>;
    filters: Filters;
    modules: Record<string, string>;
    statuses: Record<string, string>;
    report: {
        title: string;
        basis: string;
        from: string;
        to: string;
        status_label: string;
        generated_at: string;
        headers: string[];
        rows: string[][];
        total: number;
    };
};
function FiltersForm({ p }: { p: Props }) {
    const [f, setF] = useState({ ...p.filters, status: p.filters.status ?? '' });
    return (
        <form
            className="ep-card rp-filters"
            onSubmit={(e) => {
                e.preventDefault();
                router.get('/reports', f);
            }}
        >
            <Field label="Jenis laporan" error={p.errors?.module}>
                <select
                    value={f.module}
                    onChange={(e) => {
                        const module = e.target.value;
                        router.get('/reports', { ...f, module, status: '' });
                    }}
                >
                    {Object.entries(p.modules).map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Tanggal awal" error={p.errors?.from}>
                <input
                    type="date"
                    required
                    value={f.from}
                    onChange={(e) => setF({ ...f, from: e.target.value })}
                />
            </Field>
            <Field label="Tanggal akhir" error={p.errors?.to}>
                <input
                    type="date"
                    required
                    min={f.from || undefined}
                    value={f.to}
                    onChange={(e) => setF({ ...f, to: e.target.value })}
                />
            </Field>
            <Field label="Status" error={p.errors?.status}>
                <select value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {Object.entries(p.statuses).map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            </Field>
            <button className="ep-primary">
                <SlidersHorizontal size={15} />
                Tampilkan
            </button>
        </form>
    );
}
export default function Reports(p: Props) {
    const r = p.report;
    const query = new URLSearchParams({ ...p.filters, status: p.filters.status ?? '' });
    return (
        <HrShell title="Laporan HR" active="reports" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main rp-page">
                <div className="hr-page-heading">
                    <div>
                        <h1>Laporan HR</h1>
                        <p>Rekap data sesuai periode, siap diunduh untuk kebutuhan tim.</p>
                    </div>
                </div>
                <FiltersForm key={JSON.stringify(p.filters)} p={p} />
                <RecordErrors errors={p.errors ?? {}} />
                <section className="hr-panel">
                    <div className="rp-heading">
                        <div>
                            <h2>{r.title}</h2>
                            <p>
                                {r.basis}: {date(r.from)}–{date(r.to)} · {r.status_label}
                            </p>
                            <small>Dibuat {date(r.generated_at, true)} WIB</small>
                        </div>
                        <div className="rp-downloads">
                            {r.total <= 1000 ? (
                                <a
                                    className="ep-secondary"
                                    href={`/reports/export?${query}&format=xlsx`}
                                >
                                    <Download size={15} />
                                    Excel (.xlsx)
                                </a>
                            ) : (
                                <span className="rp-disabled">Excel: maksimal 1.000 baris</span>
                            )}
                            {r.total <= 200 ? (
                                <a
                                    className="ep-secondary"
                                    href={`/reports/export?${query}&format=pdf`}
                                >
                                    <Download size={15} />
                                    PDF
                                </a>
                            ) : (
                                <span className="rp-disabled">PDF: maksimal 200 baris</span>
                            )}
                        </div>
                    </div>
                    <p className="rp-count">
                        {r.total.toLocaleString('id-ID')} baris ditemukan · Pratinjau{' '}
                        {r.rows.length} baris
                    </p>
                    <div className="hr-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    {r.headers.map((h) => (
                                        <th key={h}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {r.rows.map((row, i) => (
                                    <tr key={i}>
                                        {row.map((v, j) => (
                                            <td key={j}>{v === '' ? '—' : v}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!r.rows.length && (
                        <p className="hr-empty">
                            Tidak ada data untuk periode dan status ini. Sesuaikan filter atau
                            lengkapi catatan pada modul terkait.
                        </p>
                    )}
                </section>
                <p className="tr-caption rp-note">
                    Periode maksimal 366 hari. Ekspor mengambil seluruh hasil filter, hingga 1.000
                    baris Excel atau 200 baris PDF. Persempit filter untuk hasil yang lebih besar.
                    Uraian kasus, kontak pribadi dan berkas tidak disertakan.
                </p>
                <div className="rp-links">
                    <Link href="/applications">Buka rekrutmen</Link>
                    <Link href="/trainings">Buka pelatihan</Link>
                    <Link href="/contracts">Buka kontrak</Link>
                    <Link href="/discipline">Buka pelanggaran & SP</Link>
                </div>
            </main>
        </HrShell>
    );
}
