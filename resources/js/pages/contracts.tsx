import { Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, Download, Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import EmploymentTabs from '../components/employment-tabs';
import { applicationDate as date } from '../components/application-format';
import { StatusBadge, type StatusVariant } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import { RecordPagination } from '../components/record-fields';
import type { ContractRecord, MissingContractEmployee } from '../types/contracts';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';
import '../css/trainings.css';
import '../css/contracts.css';

type Props = {
    auth: { user: HrUser };
    flash: { success: string | null };
    errors?: Record<string, string>;
    mode: 'index' | 'create' | 'edit' | 'show' | 'missing';
    canManage: boolean;
    contract?: ContractRecord;
    contracts?: {
        data: ContractRecord[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    missing?: {
        data: MissingContractEmployee[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    missingCount?: number;
    selectedId?: number | null;
    defaultStart?: string | null;
    defaultType?: 'pkwt' | 'pkwtt';
    employees?: { id: number; full_name: string; employee_number: string }[];
    filters?: { q?: string; attention?: string; employee_q?: string; status?: string };
    documents?: { id: number; version: number; size_bytes: number; created_at: string }[];
    events?: {
        id: number;
        action: string;
        actor: string;
        reason: string | null;
        created_at: string;
    }[];
};
const tones: Record<string, StatusVariant> = {
    active: 'success',
    scheduled: 'submitted',
    expired: 'expired',
    voided: 'failed',
};
const actions: Record<string, string> = {
    created: 'Kontrak dicatat',
    updated: 'Data dikoreksi',
    voided: 'Catatan dibatalkan',
    document_uploaded: 'Berkas diunggah',
};
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="ep-field tr-field">
            <span>{label}</span>
            {children}
            {error && (
                <small className="ep-error" role="alert">
                    {error}
                </small>
            )}
        </label>
    );
}
function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {['version', 'contract'].map(
                (key) =>
                    errors[key] && (
                        <p className="ep-alert" role="alert" key={key}>
                            {errors[key]}
                        </p>
                    ),
            )}
        </>
    );
}
function ContractForm({ p }: { p: Props }) {
    const c = p.contract;
    const [employeeSearch, setEmployeeSearch] = useState(p.filters?.employee_q ?? '');
    const [selected, setSelected] = useState(
        () => p.employees?.find((e) => e.id === p.selectedId) ?? null,
    );
    const f = useForm({
        employee_id: c?.employee_id.toString() ?? p.selectedId?.toString() ?? '',
        contract_number: c?.contract_number ?? '',
        type: c?.type ?? p.defaultType ?? 'pkwt',
        starts_on: c?.starts_on ?? p.defaultStart ?? '',
        ends_on: c?.ends_on ?? '',
        notes: c?.notes ?? '',
        reason: '',
        version: c?.version ?? 1,
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
                            '/contracts/create',
                            { employee_q: employeeSearch },
                            { preserveState: true, preserveScroll: true },
                        );
                    }}
                >
                    <input
                        aria-label="Cari pegawai"
                        placeholder="Cari nama atau nomor pegawai"
                        value={employeeSearch}
                        onChange={(e) => setEmployeeSearch(e.target.value)}
                    />
                    <button className="ep-secondary">Cari pegawai</button>
                </form>
            )}
            <form
                className="ep-card ap-stacked tr-schedule"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (c) f.put(`/contracts/${c.id}`);
                    else f.post('/contracts');
                }}
            >
                <Errors errors={f.errors} />
                {c ? (
                    <p className="tr-copy">
                        <strong>{c.employee_name}</strong> · {c.employee_number}
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
                            Maksimal 20 hasil pencarian. Pegawai tidak dapat diganti setelah kontrak
                            disimpan.
                        </small>
                    </Field>
                )}
                <div className="tr-form-grid">
                    <Field label="Nomor kontrak" error={f.errors.contract_number}>
                        <input
                            required
                            maxLength={100}
                            value={f.data.contract_number}
                            onChange={(e) => f.setData('contract_number', e.target.value)}
                        />
                    </Field>
                    <Field label="Jenis kontrak" error={f.errors.type}>
                        <select
                            value={f.data.type}
                            onChange={(e) => {
                                const type = e.target.value as 'pkwt' | 'pkwtt';
                                f.setData((d) => ({
                                    ...d,
                                    type,
                                    ends_on: type === 'pkwtt' ? '' : d.ends_on,
                                }));
                            }}
                        >
                            <option value="pkwt">PKWT · Waktu tertentu</option>
                            <option value="pkwtt">PKWTT · Waktu tidak tertentu</option>
                        </select>
                    </Field>
                    <Field label="Tanggal mulai" error={f.errors.starts_on}>
                        <input
                            type="date"
                            required
                            value={f.data.starts_on}
                            onChange={(e) => f.setData('starts_on', e.target.value)}
                        />
                    </Field>
                    {f.data.type === 'pkwt' ? (
                        <Field label="Tanggal berakhir" error={f.errors.ends_on}>
                            <input
                                type="date"
                                required
                                min={f.data.starts_on || undefined}
                                value={f.data.ends_on}
                                onChange={(e) => f.setData('ends_on', e.target.value)}
                            />
                        </Field>
                    ) : (
                        <p className="tr-caption">PKWTT dicatat tanpa tanggal berakhir.</p>
                    )}
                </div>
                <Field label="Catatan HR (opsional)" error={f.errors.notes}>
                    <textarea
                        maxLength={3000}
                        rows={3}
                        value={f.data.notes}
                        onChange={(e) => f.setData('notes', e.target.value)}
                    />
                </Field>
                {c && (
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
                )}
                <p className="tr-caption">
                    Perpanjangan dicatat sebagai kontrak baru setelah periode sebelumnya berakhir.
                    Koreksi dipakai untuk memperbaiki data yang salah.
                </p>
                <div className="tr-actions">
                    <button disabled={f.processing} className="ep-primary">
                        {f.processing ? 'Menyimpan…' : c ? 'Simpan koreksi' : 'Simpan kontrak'}
                    </button>
                    <Link className="ep-back" href={c ? `/contracts/${c.id}` : '/contracts'}>
                        Batal
                    </Link>
                </div>
            </form>
        </>
    );
}
function Documents({ p }: { p: Props }) {
    const c = p.contract!;
    const f = useForm<{ document: File | null }>({ document: null });
    const input = useRef<HTMLInputElement>(null);
    return (
        <section className="ep-card">
            <h2>Berkas kontrak</h2>
            {p.documents?.length ? (
                p.documents.map((d) => (
                    <a
                        key={d.id}
                        className="tr-material"
                        href={`/contracts/${c.id}/documents/${d.id}`}
                    >
                        <Download size={16} />
                        <span>
                            Kontrak · Versi {d.version}
                            <small>
                                {date(d.created_at, true)} WIB · {Math.ceil(d.size_bytes / 1024)}{' '}
                                KiB
                            </small>
                        </span>
                    </a>
                ))
            ) : (
                <p className="tr-copy">Belum ada berkas kontrak.</p>
            )}
            {c.status !== 'voided' && (
                <form
                    className="ap-stacked"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(`/contracts/${c.id}/documents`, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                f.reset();
                                if (input.current) input.current.value = '';
                            },
                        });
                    }}
                >
                    <Field label="Unggah PDF kontrak" error={f.errors.document}>
                        <input
                            ref={input}
                            type="file"
                            required
                            accept=".pdf,application/pdf"
                            onChange={(e) => f.setData('document', e.target.files?.[0] ?? null)}
                        />
                    </Field>
                    <p className="tr-caption">
                        Maksimal 5 MiB. Setiap unggahan disimpan sebagai versi baru.
                    </p>
                    <button className="ep-secondary" disabled={f.processing}>
                        {f.processing ? 'Mengunggah…' : 'Simpan berkas'}
                    </button>
                </form>
            )}
        </section>
    );
}
function VoidForm({ c }: { c: ContractRecord }) {
    const f = useForm({ version: c.version, reason: '', confirmed: false });
    return (
        <section className="ep-card ct-void">
            <h2>Batalkan catatan</h2>
            <p className="tr-copy">
                Untuk catatan yang salah dimasukkan. Riwayat dan berkas tetap tersimpan.
            </p>
            <form
                className="ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (f.data.confirmed)
                        f.post(`/contracts/${c.id}/void`, { preserveScroll: true });
                }}
            >
                <Errors errors={f.errors} />
                <Field label="Alasan pembatalan" error={f.errors.reason}>
                    <textarea
                        rows={3}
                        required
                        minLength={10}
                        maxLength={1000}
                        value={f.data.reason}
                        onChange={(e) => f.setData('reason', e.target.value)}
                    />
                </Field>
                <label className="ct-confirm">
                    <input
                        type="checkbox"
                        checked={f.data.confirmed}
                        onChange={(e) => f.setData('confirmed', e.target.checked)}
                    />
                    Saya sudah memeriksa catatan ini.
                </label>
                <button className="ep-secondary" disabled={f.processing || !f.data.confirmed}>
                    Batalkan catatan kontrak
                </button>
            </form>
        </section>
    );
}
const employmentStatuses: Record<string, string> = {
    contract: 'Kontrak',
    probation: 'Percobaan',
    intern: 'Magang',
    permanent: 'Tetap',
};
function MissingList({ p }: { p: Props }) {
    const [status, setStatus] = useState(p.filters?.status ?? '');
    const list = p.missing;
    return (
        <>
            <form
                className="tr-list-filters"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/contracts/missing', { status });
                }}
            >
                <select
                    aria-label="Status kepegawaian"
                    value={status}
                    onChange={(e) => {
                        setStatus(e.target.value);
                        router.get('/contracts/missing', { status: e.target.value });
                    }}
                >
                    <option value="">Semua status kepegawaian</option>
                    {Object.entries(employmentStatuses).map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            </form>
            <section className="hr-panel">
                <div className="tr-panel-heading">
                    <h2>{list?.total ?? 0} pegawai belum punya kontrak berjalan</h2>
                    <p>Pegawai kontrak dan percobaan ditampilkan lebih dulu.</p>
                </div>
                <div className="hr-table-scroll">
                    <table className="hr-table">
                        <thead>
                            <tr>
                                <th>Pegawai</th>
                                <th>Status kepegawaian</th>
                                <th>Bergabung</th>
                                <th>
                                    <span className="sr-only">Tindakan</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {list?.data.map((e) => (
                                <tr key={e.id}>
                                    <td>
                                        <Link href={`/employees/${e.id}`}>
                                            <strong>{e.full_name}</strong>
                                        </Link>
                                        <small className="tr-sub">
                                            {e.employee_number} · {e.department}
                                        </small>
                                    </td>
                                    <td>{e.status_label}</td>
                                    <td>{date(e.join_date)}</td>
                                    <td>
                                        {p.canManage && (
                                            <Link
                                                className="ct-row-action"
                                                href={`/contracts/create?employee_id=${e.id}&starts_on=${e.join_date}`}
                                            >
                                                Catat kontrak <ArrowRight size={14} />
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!list?.data.length && (
                    <p className="hr-empty">Tidak ada pegawai yang cocok dengan filter ini.</p>
                )}
            </section>
            {list && <RecordPagination total={list.total} links={list.links} />}
        </>
    );
}
export default function Contracts(p: Props) {
    const c = p.contract;
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [attention, setAttention] = useState(p.filters?.attention ?? '');
    const title =
        p.mode === 'create'
            ? 'Tambah kontrak'
            : p.mode === 'edit'
              ? 'Koreksi kontrak'
              : p.mode === 'show'
                ? 'Detail kontrak'
                : p.mode === 'missing'
                  ? 'Pegawai tanpa kontrak'
                  : 'Kontrak kerja';
    return (
        <HrShell title={title} active="contracts" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main tr-page">
                <EmploymentTabs active="contracts" canManage={p.canManage} />
                {p.mode !== 'index' && (
                    <Link
                        className="ep-back"
                        href={p.mode === 'edit' ? `/contracts/${c!.id}` : '/contracts'}
                    >
                        <ArrowLeft size={14} />
                        Kembali ke {p.mode === 'edit' ? 'detail kontrak' : 'daftar kontrak'}
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{p.mode === 'show' ? c!.contract_number : title}</h1>
                        <p>
                            {p.mode === 'index'
                                ? 'Pantau periode kerja dan kontrak yang perlu ditinjau.'
                                : p.mode === 'show'
                                  ? `${c!.type.toUpperCase()} · ${c!.employee_name}`
                                  : p.mode === 'missing'
                                    ? 'Pegawai aktif yang belum punya kontrak berjalan hari ini (WIB).'
                                    : 'Catat periode sesuai dokumen kontrak pegawai.'}
                        </p>
                    </div>
                    {p.mode === 'index' && p.canManage && (
                        <Link href="/contracts/create" className="ep-primary">
                            <Plus size={15} />
                            Tambah kontrak
                        </Link>
                    )}
                    {p.mode === 'show' && p.canManage && c!.status !== 'voided' && (
                        <Link href={`/contracts/${c!.id}/edit`} className="ep-secondary">
                            Koreksi data
                        </Link>
                    )}
                </div>
                {p.flash?.success && (
                    <p role="status" className="ep-alert">
                        {p.flash.success}
                    </p>
                )}
                <Errors errors={p.errors ?? {}} />
                {(p.mode === 'create' || p.mode === 'edit') && (
                    <ContractForm key={c ? `${c.id}-${c.version}` : 'new'} p={p} />
                )}
                {p.mode === 'missing' && <MissingList p={p} />}
                {p.mode === 'index' && (
                    <>
                        {p.missingCount ? (
                            <p className="ep-alert">
                                {p.missingCount} pegawai aktif belum punya kontrak berjalan.{' '}
                                <Link href="/contracts/missing">Lihat daftar</Link>
                            </p>
                        ) : null}
                        <form
                            className="tr-list-filters"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get('/contracts', { q, attention });
                            }}
                        >
                            <input
                                aria-label="Cari kontrak"
                                placeholder="Cari nomor kontrak, nama atau nomor pegawai"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <select
                                aria-label="Perhatian kontrak"
                                value={attention}
                                onChange={(e) => {
                                    setAttention(e.target.value);
                                    router.get('/contracts', { q, attention: e.target.value });
                                }}
                            >
                                <option value="">Semua kontrak</option>
                                <option value="due">Berakhir dalam 30 hari</option>
                                <option value="expired">Sudah berakhir</option>
                            </select>
                            <button className="ep-secondary">Cari</button>
                        </form>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>{p.contracts?.total ?? 0} kontrak tercatat</h2>
                                {p.filters?.attention && (
                                    <p>PKWT pegawai aktif, berdasarkan tanggal WIB.</p>
                                )}
                            </div>
                            <div className="hr-table-scroll">
                                <table className="hr-table">
                                    <thead>
                                        <tr>
                                            <th>Pegawai</th>
                                            <th>Nomor kontrak</th>
                                            <th>Jenis</th>
                                            <th>Periode</th>
                                            <th>Status</th>
                                            <th>
                                                <span className="sr-only">Detail</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.contracts?.data.map((c) => (
                                            <tr key={c.id}>
                                                <td>
                                                    <strong>{c.employee_name}</strong>
                                                    <small className="tr-sub">
                                                        {c.employee_number}
                                                        {c.employee_archived ? ' · Diarsipkan' : ''}
                                                    </small>
                                                </td>
                                                <td>{c.contract_number}</td>
                                                <td>{c.type.toUpperCase()}</td>
                                                <td>
                                                    {date(c.starts_on)}
                                                    <small className="tr-sub">
                                                        s.d.{' '}
                                                        {c.ends_on
                                                            ? date(c.ends_on)
                                                            : 'Tanpa tanggal akhir'}
                                                    </small>
                                                </td>
                                                <td>
                                                    <StatusBadge variant={tones[c.status]}>
                                                        {c.status_label}
                                                    </StatusBadge>
                                                </td>
                                                <td>
                                                    <Link
                                                        aria-label={`Detail kontrak ${c.contract_number}`}
                                                        href={`/contracts/${c.id}`}
                                                    >
                                                        <ArrowRight size={16} />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.contracts?.data.length && (
                                <p className="hr-empty">
                                    Belum ada kontrak yang cocok.{' '}
                                    {p.canManage
                                        ? 'Tambahkan kontrak atau sesuaikan pencarian.'
                                        : 'Sesuaikan pencarian atau hubungi tim HR.'}
                                </p>
                            )}
                        </section>
                        {p.contracts && p.contracts.total > 15 && (
                            <nav className="tr-pagination" aria-label="Halaman kontrak">
                                {p.contracts.links.map((l, i) =>
                                    l.url ? (
                                        <Link
                                            key={i}
                                            href={l.url}
                                            aria-current={l.active ? 'page' : undefined}
                                        >
                                            {i === 0
                                                ? 'Sebelumnya'
                                                : i === p.contracts!.links.length - 1
                                                  ? 'Berikutnya'
                                                  : l.label}
                                        </Link>
                                    ) : (
                                        <span key={i}>
                                            {i === 0
                                                ? 'Sebelumnya'
                                                : i === p.contracts!.links.length - 1
                                                  ? 'Berikutnya'
                                                  : l.label}
                                        </span>
                                    ),
                                )}
                            </nav>
                        )}
                    </>
                )}
                {p.mode === 'show' && c && (
                    <>
                        <section className="ep-card ct-summary">
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
                                <span className="tr-label">Tanggal mulai</span>
                                <p>{date(c.starts_on)}</p>
                            </div>
                            <div>
                                <span className="tr-label">Tanggal berakhir</span>
                                <p>{c.ends_on ? date(c.ends_on) : 'Tanpa tanggal akhir'}</p>
                            </div>
                            <StatusBadge variant={tones[c.status]}>{c.status_label}</StatusBadge>
                        </section>
                        {p.canManage ? (
                            <div className="tr-layout">
                                <div>
                                    <Documents p={p} />
                                    {c.notes && (
                                        <section className="ep-card">
                                            <h2>Catatan HR</h2>
                                            <p className="tr-copy">{c.notes}</p>
                                        </section>
                                    )}
                                </div>
                                <aside>
                                    <section className="ep-card">
                                        <h2>Riwayat kontrak</h2>
                                        <ProcessTimeline
                                            items={(p.events ?? []).map((e) => ({
                                                label: `${actions[e.action] ?? e.action} · #${e.id}`,
                                                time: `${date(e.created_at, true)} WIB`,
                                                state: 'done' as const,
                                                note: [e.actor, e.reason]
                                                    .filter(Boolean)
                                                    .join(' · '),
                                            }))}
                                        />
                                    </section>
                                    {c.status !== 'voided' && (
                                        <VoidForm key={`${c.id}-${c.version}`} c={c} />
                                    )}
                                </aside>
                            </div>
                        ) : (
                            <p className="tr-caption">
                                Berkas, catatan dan riwayat dikelola oleh Admin atau Staf HR.
                            </p>
                        )}
                    </>
                )}
            </main>
        </HrShell>
    );
}
