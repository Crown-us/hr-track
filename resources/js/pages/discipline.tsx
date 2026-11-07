import { Link, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ArrowLeft, ArrowRight, Download, Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import EmploymentTabs from '../components/employment-tabs';
import {
    RecordField as Field,
    RecordErrors as Errors,
    RecordPagination,
} from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import { StatusBadge, type StatusVariant } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';
import '../css/trainings.css';
import '../css/discipline.css';

type Case = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    employee_archived: boolean;
    reference_number: string;
    kind: string;
    kind_label: string;
    subject: string;
    description: string;
    incident_on: string;
    follow_up_on: string | null;
    issued_on: string | null;
    valid_until: string | null;
    validity: string | null;
    validity_label: string | null;
    status: string;
    status_label: string;
    version: number;
};
type Employee = { id: number; full_name: string; employee_number: string };
type Props = {
    auth: { user: HrUser };
    flash: { success: string | null; warning?: string | null };
    errors?: Record<string, string>;
    mode: 'index' | 'create' | 'edit' | 'show';
    case?: Case;
    cases?: {
        data: Case[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    today?: string;
    filters?: { q?: string; kind?: string; status?: string; employee_q?: string };
    employees?: Employee[];
    documents?: { id: number; version: number; size_bytes: number; created_at: string }[];
    events?: {
        id: number;
        action: string;
        actor: string;
        reason: string | null;
        created_at: string;
    }[];
};
const kinds: Record<string, string> = {
    violation: 'Pelanggaran',
    sp1: 'SP 1',
    sp2: 'SP 2',
    sp3: 'SP 3',
    counseling: 'Konseling',
};
const statuses: Record<string, string> = {
    open: 'Perlu tindak lanjut',
    resolved: 'Selesai',
    voided: 'Dibatalkan',
};
const tones: Record<string, StatusVariant> = {
    open: 'pending',
    resolved: 'success',
    voided: 'failed',
};
const validityTones: Record<string, StatusVariant> = {
    active: 'success',
    expired: 'expired',
    unset: 'pending',
};
const actions: Record<string, string> = {
    created: 'Catatan dibuat',
    updated: 'Data dikoreksi',
    document_uploaded: 'Berkas diunggah',
    resolved: 'Tindak lanjut selesai',
    voided: 'Catatan dibatalkan',
};

function CaseForm({ p }: { p: Props }) {
    const c = p.case;
    const [search, setSearch] = useState(p.filters?.employee_q ?? '');
    const [selected, setSelected] = useState<Employee | null>(null);
    const f = useForm({
        employee_id: c?.employee_id.toString() ?? '',
        reference_number: c?.reference_number ?? '',
        kind: c?.kind ?? 'violation',
        incident_on: c?.incident_on ?? p.today ?? '',
        subject: c?.subject ?? '',
        description: c?.description ?? '',
        follow_up_on: c?.follow_up_on ?? '',
        issued_on: c ? (c.issued_on ?? '') : (p.today ?? ''),
        valid_until: c?.valid_until ?? '',
        reason: '',
        version: c?.version ?? 1,
        document: null as File | null,
    });
    const options = [...(p.employees ?? [])];
    if (selected && !options.some((e) => e.id === selected.id)) options.push(selected);
    return (
        <>
            {!c && (
                <form
                    className="tr-search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            '/discipline/create',
                            { employee_q: search },
                            { preserveState: true, preserveScroll: true },
                        );
                    }}
                >
                    <input
                        aria-label="Cari pegawai"
                        placeholder="Nama atau nomor pegawai"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <button className="ep-secondary">Cari pegawai</button>
                </form>
            )}
            <form
                className="ep-card ap-stacked tr-schedule"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (c) f.put(`/discipline/${c.id}`);
                    else f.post('/discipline', { forceFormData: true });
                }}
            >
                <Errors errors={f.errors} />
                {c ? (
                    <p className="tr-copy">
                        <strong>{c.employee_name}</strong> · {c.employee_number} · {c.kind_label}
                    </p>
                ) : (
                    <Field label="Pegawai" error={f.errors.employee_id}>
                        <select
                            required
                            value={f.data.employee_id}
                            onChange={(e) => {
                                f.setData('employee_id', e.target.value);
                                setSelected(
                                    options.find((p) => p.id.toString() === e.target.value) ?? null,
                                );
                            }}
                        >
                            <option value="">Pilih pegawai aktif</option>
                            {options.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.full_name} · {e.employee_number}
                                </option>
                            ))}
                        </select>
                        <small className="tr-sub">
                            Maksimal 20 hasil. Gunakan pencarian untuk pegawai lainnya.
                        </small>
                    </Field>
                )}
                <div className="tr-form-grid">
                    <Field label="Nomor catatan" error={f.errors.reference_number}>
                        <input
                            required
                            maxLength={100}
                            value={f.data.reference_number}
                            onChange={(e) => f.setData('reference_number', e.target.value)}
                        />
                    </Field>
                    {!c && (
                        <Field label="Jenis catatan" error={f.errors.kind}>
                            <select
                                value={f.data.kind}
                                onChange={(e) => f.setData('kind', e.target.value)}
                            >
                                {Object.entries(kinds).map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Tanggal kejadian" error={f.errors.incident_on}>
                        <input
                            type="date"
                            required
                            max={p.today}
                            value={f.data.incident_on}
                            onChange={(e) => f.setData('incident_on', e.target.value)}
                        />
                    </Field>
                    <Field
                        label="Tindak lanjut berikutnya (opsional)"
                        error={f.errors.follow_up_on}
                    >
                        <input
                            type="date"
                            min={f.data.incident_on || undefined}
                            value={f.data.follow_up_on}
                            onChange={(e) => f.setData('follow_up_on', e.target.value)}
                        />
                    </Field>
                </div>
                {f.data.kind.startsWith('sp') && (
                    <div className="tr-form-grid">
                        <Field label="Tanggal terbit surat" error={f.errors.issued_on}>
                            <input
                                type="date"
                                required
                                min={f.data.incident_on || undefined}
                                max={p.today}
                                value={f.data.issued_on}
                                onChange={(e) => f.setData('issued_on', e.target.value)}
                            />
                        </Field>
                        <Field label="Berlaku sampai (opsional)" error={f.errors.valid_until}>
                            <input
                                type="date"
                                min={f.data.issued_on || undefined}
                                value={f.data.valid_until}
                                onChange={(e) => f.setData('valid_until', e.target.value)}
                            />
                            <small className="tr-sub">
                                Kosongkan untuk 6 bulan sejak tanggal terbit. Ubah jika peraturan
                                perusahaan atau PKB menentukan lain.
                            </small>
                        </Field>
                    </div>
                )}
                <Field label="Judul catatan" error={f.errors.subject}>
                    <input
                        required
                        maxLength={255}
                        value={f.data.subject}
                        onChange={(e) => f.setData('subject', e.target.value)}
                    />
                </Field>
                <Field label="Uraian kejadian / konseling" error={f.errors.description}>
                    <textarea
                        required
                        rows={5}
                        maxLength={5000}
                        value={f.data.description}
                        onChange={(e) => f.setData('description', e.target.value)}
                    />
                </Field>
                {c ? (
                    <Field label="Alasan koreksi" error={f.errors.reason}>
                        <textarea
                            required
                            minLength={10}
                            maxLength={1000}
                            rows={3}
                            value={f.data.reason}
                            onChange={(e) => f.setData('reason', e.target.value)}
                        />
                    </Field>
                ) : (
                    <Field
                        label={
                            f.data.kind.startsWith('sp')
                                ? 'PDF surat peringatan'
                                : 'Berkas PDF (opsional)'
                        }
                        error={f.errors.document}
                    >
                        <input
                            type="file"
                            accept=".pdf,application/pdf"
                            required={f.data.kind.startsWith('sp')}
                            onChange={(e) => f.setData('document', e.target.files?.[0] ?? null)}
                        />
                        <small className="tr-sub">
                            Maksimal 5 MiB. Berkas hanya dapat dibuka Admin/Staf HR.
                        </small>
                    </Field>
                )}
                <div className="tr-actions">
                    <button className="ep-primary" disabled={f.processing}>
                        {f.processing ? 'Menyimpan…' : c ? 'Simpan koreksi' : 'Simpan catatan'}
                    </button>
                    <Link className="ep-back" href={c ? `/discipline/${c.id}` : '/discipline'}>
                        Batal
                    </Link>
                </div>
            </form>
        </>
    );
}
function CaseActions({ c }: { c: Case }) {
    const f = useForm({
        version: c.version,
        status: c.status === 'open' ? 'resolved' : 'voided',
        reason: '',
        confirmed: false,
    });
    return (
        <section className="ep-card">
            <h2>Hasil tindak lanjut</h2>
            <form
                className="ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (f.data.confirmed) f.post(`/discipline/${c.id}/status`);
                }}
            >
                <Errors errors={f.errors} />
                <Field label="Tindakan" error={f.errors.status}>
                    <select
                        value={f.data.status}
                        onChange={(e) => f.setData('status', e.target.value)}
                    >
                        {c.status === 'open' && (
                            <option value="resolved">Selesaikan catatan</option>
                        )}
                        <option value="voided">Batalkan catatan salah</option>
                    </select>
                </Field>
                <Field
                    label={
                        f.data.status === 'resolved' ? 'Hasil tindak lanjut' : 'Alasan pembatalan'
                    }
                    error={f.errors.reason}
                >
                    <textarea
                        required
                        rows={4}
                        minLength={10}
                        maxLength={2000}
                        value={f.data.reason}
                        onChange={(e) => f.setData('reason', e.target.value)}
                    />
                </Field>
                <label className="ds-confirm">
                    <input
                        type="checkbox"
                        checked={f.data.confirmed}
                        onChange={(e) => f.setData('confirmed', e.target.checked)}
                    />
                    Saya sudah memeriksa catatan dan hasilnya.
                </label>
                <p className="tr-caption">
                    Riwayat dan berkas tetap tersimpan setelah catatan ditutup.
                </p>
                <button className="ep-secondary" disabled={f.processing || !f.data.confirmed}>
                    Simpan hasil
                </button>
            </form>
        </section>
    );
}
function CaseDocuments({ p }: { p: Props }) {
    const c = p.case!;
    const f = useForm<{ document: File | null }>({ document: null });
    const input = useRef<HTMLInputElement>(null);
    return (
        <section className="ep-card">
            <h2>Berkas pendukung</h2>
            {p.documents?.length ? (
                p.documents.map((d) => (
                    <a
                        className="tr-material"
                        key={d.id}
                        href={`/discipline/${c.id}/documents/${d.id}`}
                    >
                        <Download size={16} />
                        <span>
                            Berkas PDF · Versi {d.version}
                            <small>
                                {date(d.created_at, true)} WIB · {Math.ceil(d.size_bytes / 1024)}{' '}
                                KiB
                            </small>
                        </span>
                    </a>
                ))
            ) : (
                <p className="tr-copy">Belum ada berkas pendukung.</p>
            )}
            {c.status === 'open' && (
                <form
                    className="ap-stacked"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(`/discipline/${c.id}/documents`, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                f.reset();
                                if (input.current) input.current.value = '';
                            },
                        });
                    }}
                >
                    <Field label="Unggah PDF baru" error={f.errors.document}>
                        <input
                            ref={input}
                            type="file"
                            required
                            accept=".pdf,application/pdf"
                            onChange={(e) => f.setData('document', e.target.files?.[0] ?? null)}
                        />
                    </Field>
                    <p className="tr-caption">Maksimal 5 MiB. Versi lama tetap tersimpan.</p>
                    <button className="ep-secondary" disabled={f.processing}>
                        Simpan berkas
                    </button>
                </form>
            )}
        </section>
    );
}
export default function Discipline(p: Props) {
    const c = p.case;
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [kind, setKind] = useState(p.filters?.kind ?? '');
    const [status, setStatus] = useState(p.filters?.status ?? '');
    const title =
        p.mode === 'create'
            ? 'Tambah catatan'
            : p.mode === 'edit'
              ? 'Koreksi catatan'
              : p.mode === 'show'
                ? c!.reference_number
                : 'Pelanggaran & SP';
    return (
        <HrShell title="Pelanggaran & SP" active="contracts" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main tr-page ds-page">
                <EmploymentTabs active="discipline" canManage />
                {p.mode !== 'index' && (
                    <Link
                        className="ep-back"
                        href={p.mode === 'edit' ? `/discipline/${c!.id}` : '/discipline'}
                    >
                        <ArrowLeft size={14} />
                        Kembali ke {p.mode === 'edit' ? 'detail' : 'daftar'}
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {c
                                ? `${c.employee_name} · ${c.kind_label}`
                                : 'Catatan kejadian, surat peringatan, dan tindak lanjut pegawai.'}
                        </p>
                    </div>
                    {p.mode === 'index' && (
                        <Link className="ep-primary" href="/discipline/create">
                            <Plus size={15} />
                            Tambah catatan
                        </Link>
                    )}
                    {p.mode === 'show' && c!.status === 'open' && (
                        <Link className="ep-secondary" href={`/discipline/${c!.id}/edit`}>
                            Koreksi data
                        </Link>
                    )}
                </div>
                {p.flash?.success && (
                    <p role="status" className="ep-alert">
                        {p.flash.success}
                    </p>
                )}
                {p.flash?.warning && (
                    <p role="alert" className="ep-alert">
                        {p.flash.warning}
                    </p>
                )}
                <Errors errors={p.errors ?? {}} />
                {(p.mode === 'create' || p.mode === 'edit') && (
                    <CaseForm key={c ? `${c.id}-${c.version}` : 'new'} p={p} />
                )}
                {p.mode === 'index' && (
                    <>
                        <form
                            className="tr-list-filters"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get('/discipline', { q, kind, status });
                            }}
                        >
                            <input
                                aria-label="Cari catatan"
                                placeholder="Nama, nomor pegawai, catatan atau judul"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <select
                                aria-label="Jenis catatan"
                                value={kind}
                                onChange={(e) => {
                                    setKind(e.target.value);
                                    router.get('/discipline', { q, status, kind: e.target.value });
                                }}
                            >
                                <option value="">Semua jenis</option>
                                {Object.entries(kinds).map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label="Status catatan"
                                value={status}
                                onChange={(e) => {
                                    setStatus(e.target.value);
                                    router.get('/discipline', { q, kind, status: e.target.value });
                                }}
                            >
                                <option value="">Semua status</option>
                                {Object.entries(statuses).map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                            <button className="ep-secondary">Cari</button>
                        </form>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>{p.cases?.total ?? 0} catatan tersimpan</h2>
                            </div>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Pegawai / catatan</th>
                                            <th>Jenis</th>
                                            <th>Kejadian</th>
                                            <th>Tindak lanjut</th>
                                            <th>Masa berlaku</th>
                                            <th>Status</th>
                                            <th>
                                                <span className="sr-only">Detail</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.cases?.data.map((c) => (
                                            <tr key={c.id}>
                                                <td>
                                                    <strong>{c.employee_name}</strong>
                                                    <small className="tr-sub">
                                                        {c.reference_number} · {c.subject}
                                                    </small>
                                                </td>
                                                <td>{c.kind_label}</td>
                                                <td>{date(c.incident_on)}</td>
                                                <td>
                                                    {c.follow_up_on ? date(c.follow_up_on) : '—'}
                                                </td>
                                                <td>
                                                    {c.validity ? (
                                                        <>
                                                            <StatusBadge
                                                                variant={validityTones[c.validity]}
                                                            >
                                                                {c.validity_label}
                                                            </StatusBadge>
                                                            {c.valid_until && (
                                                                <small className="tr-sub">
                                                                    s.d. {date(c.valid_until)}
                                                                </small>
                                                            )}
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td>
                                                    <StatusBadge variant={tones[c.status]}>
                                                        {c.status_label}
                                                    </StatusBadge>
                                                </td>
                                                <td>
                                                    <Link
                                                        aria-label={`Detail ${c.reference_number}`}
                                                        href={`/discipline/${c.id}`}
                                                    >
                                                        <ArrowRight size={16} />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.cases?.data.length && (
                                <p className="hr-empty">
                                    Belum ada catatan yang cocok. Tambahkan catatan atau sesuaikan
                                    pencarian.
                                </p>
                            )}
                        </section>
                        {p.cases && (
                            <RecordPagination total={p.cases.total} links={p.cases.links} />
                        )}
                    </>
                )}
                {p.mode === 'show' && c && (
                    <>
                        <section className="ep-card ds-summary">
                            <div>
                                <span className="tr-label">Pegawai</span>
                                <Link href={`/employees/${c.employee_id}`}>
                                    {c.employee_name} <ArrowRight size={14} />
                                </Link>
                                <small className="tr-sub">
                                    {c.employee_number}
                                    {c.employee_archived ? ' · Diarsipkan' : ''}
                                </small>
                            </div>
                            <div>
                                <span className="tr-label">Kejadian</span>
                                <p>{date(c.incident_on)}</p>
                            </div>
                            <div>
                                <span className="tr-label">Tindak lanjut</span>
                                <p>{c.follow_up_on ? date(c.follow_up_on) : 'Belum dijadwalkan'}</p>
                            </div>
                            <StatusBadge variant={tones[c.status]}>{c.status_label}</StatusBadge>
                        </section>
                        <div className="tr-layout">
                            <div>
                                <section className="ep-card">
                                    <h2>{c.subject}</h2>
                                    {c.validity && (
                                        <p className="tr-copy">
                                            <StatusBadge variant={validityTones[c.validity]}>
                                                {c.validity_label}
                                            </StatusBadge>{' '}
                                            {c.issued_on && c.valid_until
                                                ? `Terbit ${date(c.issued_on)} · berlaku sampai ${date(c.valid_until)}`
                                                : 'Koreksi catatan untuk mengisi tanggal terbit dan masa berlaku.'}
                                        </p>
                                    )}
                                    <p className="tr-copy">{c.description}</p>
                                </section>
                                <CaseDocuments p={p} />
                            </div>
                            <aside>
                                <section className="ep-card">
                                    <h2>Riwayat catatan</h2>
                                    <ProcessTimeline
                                        items={(p.events ?? []).map((e) => ({
                                            label: `${actions[e.action] ?? e.action} · #${e.id}`,
                                            time: `${date(e.created_at, true)} WIB`,
                                            state: 'done' as const,
                                            note: [e.actor, e.reason].filter(Boolean).join(' · '),
                                        }))}
                                    />
                                </section>
                                {c.status !== 'voided' && (
                                    <CaseActions key={`${c.id}-${c.version}`} c={c} />
                                )}
                            </aside>
                        </div>
                    </>
                )}
            </main>
        </HrShell>
    );
}
