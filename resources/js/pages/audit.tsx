import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import '../css/employees.css';
import '../css/applications.css';
import '../css/trainings.css';
import '../css/reports.css';

type Filters = { source: string; action: string; from: string; to: string };
type AuditEvent = {
    key: string;
    module: string;
    action: string;
    actor: string;
    subject_id: number | null;
    subject_url: string | null;
    created_at: string;
};
type Props = {
    auth: { user: HrUser };
    errors?: Record<string, string>;
    filters: Filters;
    modules: Record<string, string>;
    actions: string[];
    events: {
        data: AuditEvent[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

function FiltersForm({ p }: { p: Props }) {
    const [f, setF] = useState(p.filters);
    return (
        <form
            className="ep-card rp-filters"
            onSubmit={(e) => {
                e.preventDefault();
                router.get('/audit', { ...f });
            }}
        >
            <Field label="Modul" error={p.errors?.source}>
                <select
                    value={f.source}
                    onChange={(e) =>
                        router.get('/audit', { ...f, source: e.target.value, action: '' })
                    }
                >
                    <option value="">Semua modul</option>
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
                    value={f.from}
                    onChange={(e) => setF({ ...f, from: e.target.value })}
                />
            </Field>
            <Field label="Tanggal akhir" error={p.errors?.to}>
                <input
                    type="date"
                    min={f.from || undefined}
                    value={f.to}
                    onChange={(e) => setF({ ...f, to: e.target.value })}
                />
            </Field>
            <Field label="Aksi" error={p.errors?.action}>
                <select value={f.action} onChange={(e) => setF({ ...f, action: e.target.value })}>
                    <option value="">Semua aksi</option>
                    {p.actions.map((a) => (
                        <option key={a} value={a}>
                            {a}
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

export default function Audit(p: Props) {
    const { events } = p;
    return (
        <HrShell title="Jejak audit" active="audit" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main rp-page">
                <div className="hr-page-heading">
                    <div>
                        <h1>Jejak audit</h1>
                        <p>Siapa mengubah apa dan kapan, dari semua modul dalam satu daftar.</p>
                    </div>
                </div>
                <FiltersForm key={JSON.stringify(p.filters)} p={p} />
                <section className="hr-panel">
                    <p className="rp-count">
                        {events.total.toLocaleString('id-ID')} catatan
                        {events.total > 0 &&
                            ` · Menampilkan ${events.from}–${events.to}, terbaru lebih dulu`}
                    </p>
                    <div className="hr-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Waktu (WIB)</th>
                                    <th>Modul</th>
                                    <th>Aksi</th>
                                    <th>Pelaku</th>
                                    <th>Rekaman</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.data.map((e) => (
                                    <tr key={e.key}>
                                        <td>{date(e.created_at, true)}</td>
                                        <td>{e.module}</td>
                                        <td>
                                            <code>{e.action}</code>
                                        </td>
                                        <td>{e.actor}</td>
                                        <td>
                                            {e.subject_id === null ? (
                                                '—'
                                            ) : e.subject_url ? (
                                                <Link href={e.subject_url}>#{e.subject_id}</Link>
                                            ) : (
                                                `#${e.subject_id}`
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!events.data.length && (
                        <p className="hr-empty">
                            Belum ada catatan yang cocok dengan filter ini. Ubah rentang tanggal
                            atau pilih semua modul.
                        </p>
                    )}
                </section>
                <RecordPagination total={events.total} perPage={25} links={events.links} />
                <p className="tr-caption rp-note">
                    Halaman ini hanya menampilkan siapa, kapan, dan jenis aksi. Isi perubahan,
                    alasan, dan catatan tetap berada di halaman rekaman masing-masing. Kolom Rekaman
                    berisi nomor rekaman; tanda strip berarti tidak terkait satu rekaman.
                </p>
            </main>
        </HrShell>
    );
}
