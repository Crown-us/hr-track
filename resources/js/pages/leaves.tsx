import { Link, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, CalendarDays, Download, Plus, Settings2 } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import PortalShell from '../components/portal-shell';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/leave.css';

type Balance = {
    year: number;
    allocated: number;
    reserved: number;
    used: number;
    available?: number;
    version: number;
};
type Person = { id: number; full_name: string; employee_number: string; balance: Balance };
type Leave = {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    starts_on: string;
    ends_on: string;
    days: number;
    year: number;
    version: number;
    employee: Person;
    reason?: string;
    work_dates?: string[];
    has_document?: boolean;
};
type Event = {
    id: number;
    action: string;
    actor: string;
    employee?: string;
    created_at: string;
    changes: {
        reason?: string;
        from?: string;
        to?: string;
        year?: number;
        before?: number;
        after?: number;
        date?: string;
        name?: string;
    };
};
type Page<T> = {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
type Props = {
    auth: { user: HrUser };
    mode: 'index' | 'create' | 'show' | 'settings';
    isHr: boolean;
    isTeam?: boolean;
    types?: Record<string, string>;
    statuses?: Record<string, string>;
    filters?: { year: number; scope?: string; status?: string; q?: string };
    balance?: Balance;
    nextBalance?: Balance;
    requests?: Page<Leave>;
    leave?: Leave;
    actions?: string[];
    events?: Event[];
    settingEvents?: Event[];
    employees?: Page<Person>;
    holidays?: { id?: number; date: string; name: string }[];
    flash?: { success?: string };
    errors?: Record<string, string>;
};
const actionLabels: Record<string, string> = {
    approve: 'Setujui',
    reject: 'Tolak pengajuan',
    cancel: 'Batalkan pengajuan',
    approve_cancel: 'Setujui pembatalan',
    reject_cancel: 'Tolak pembatalan',
};
const eventLabels: Record<string, string> = {
    submitted: 'Pengajuan dibuat',
    approve: 'Persetujuan',
    reject: 'Pengajuan ditolak',
    cancel: 'Pembatalan diajukan',
    approve_cancel: 'Pembatalan disetujui',
    reject_cancel: 'Pembatalan ditolak',
    balance_set: 'Alokasi diperbarui',
    holiday_added: 'Hari libur ditambahkan',
    holiday_removed: 'Hari libur dihapus',
};
function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        Object.keys(errors).length > 0 && (
            <div role="alert" className="ep-alert">
                {Object.entries(errors).map(([key, error]) => (
                    <p key={key}>{error}</p>
                ))}
            </div>
        )
    );
}
function Status({ leave }: { leave: Leave }) {
    return <span className={`lv-status lv-${leave.status}`}>{leave.status_label}</span>;
}
function Metrics({ b }: { b: Balance }) {
    const available = b.available ?? b.allocated - b.reserved - b.used;
    return (
        <section className="lv-balance" aria-label={`Saldo cuti tahunan ${b.year}`}>
            <div className="lv-available">
                <span>Tersedia · {b.year}</span>
                <strong>
                    {available}
                    <small> hari</small>
                </strong>
            </div>
            <dl>
                {[
                    ['Alokasi', b.allocated],
                    ['Tertahan', b.reserved],
                    ['Terpakai', b.used],
                ].map(([label, value]) => (
                    <div key={label}>
                        <dt>{label}</dt>
                        <dd>
                            {value} <small>hari</small>
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
function History({ events }: { events: Event[] }) {
    return (
        <section className="ep-card lv-history">
            <h2>Riwayat</h2>
            {!events.length && <p className="tr-caption">Belum ada perubahan.</p>}
            <ol>
                {events.map((e) => (
                    <li key={e.id}>
                        <div>
                            <strong>
                                {e.action === 'cancel' && e.changes.to === 'cancelled'
                                    ? 'Pengajuan dibatalkan'
                                    : (eventLabels[e.action] ?? e.action)}
                            </strong>
                            <time>
                                {new Date(e.created_at).toLocaleString('id-ID', {
                                    timeZone: 'Asia/Jakarta',
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })}{' '}
                                WIB
                            </time>
                        </div>
                        <p>
                            {e.actor}
                            {e.employee ? ` · ${e.employee}` : ''}
                            {e.changes.year
                                ? ` · ${e.changes.year}: ${e.changes.before} → ${e.changes.after} hari`
                                : ''}
                            {e.changes.date ? ` · ${date(e.changes.date)} · ${e.changes.name}` : ''}
                        </p>
                        {e.changes.reason && <p>{e.changes.reason}</p>}
                    </li>
                ))}
            </ol>
        </section>
    );
}
function ApplicationForm({ p }: { p: Props }) {
    const f = useForm<{
        type: string;
        starts_on: string;
        ends_on: string;
        reason: string;
        document: File | null;
    }>({ type: 'annual', starts_on: '', ends_on: '', reason: '', document: null });
    const nextYear = f.data.starts_on.slice(0, 4) === String(p.nextBalance?.year);
    return (
        <div className="lv-form-layout">
            <form
                className="ep-card"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post('/leave');
                }}
            >
                <h2>Pengajuan baru</h2>
                <Errors errors={f.errors} />
                <div className="tr-form-grid">
                    <Field label="Jenis pengajuan">
                        <select
                            value={f.data.type}
                            onChange={(e) => f.setData('type', e.target.value)}
                        >
                            {Object.entries(p.types ?? {}).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div />
                    <Field label="Tanggal mulai">
                        <input
                            type="date"
                            required
                            value={f.data.starts_on}
                            onChange={(e) => f.setData('starts_on', e.target.value)}
                        />
                    </Field>
                    <Field label="Tanggal akhir">
                        <input
                            type="date"
                            required
                            value={f.data.ends_on}
                            onChange={(e) => f.setData('ends_on', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="Alasan pengajuan">
                    <textarea
                        required
                        minLength={10}
                        maxLength={2000}
                        rows={4}
                        value={f.data.reason}
                        onChange={(e) => f.setData('reason', e.target.value)}
                    />
                </Field>
                <Field label="Bukti pendukung (opsional)">
                    <input
                        type="file"
                        accept="application/pdf,.pdf"
                        onChange={(e) => f.setData('document', e.target.files?.[0] ?? null)}
                    />
                </Field>
                <p className="tr-caption">
                    PDF maksimal 5 MiB. Berkas hanya dapat dibuka oleh Anda dan HR.
                </p>
                <button className="ep-primary" disabled={f.processing}>
                    {f.processing ? 'Mengirim…' : 'Kirim pengajuan'}
                </button>
            </form>
            <aside>
                {(nextYear ? p.nextBalance : p.balance) && (
                    <Metrics b={(nextYear ? p.nextBalance : p.balance)!} />
                )}
                <div className="ep-card lv-guidance">
                    <CalendarDays size={20} />
                    <h2>Hari kerja yang dihitung</h2>
                    <p>
                        Senin–Jumat, kecuali hari libur kantor. Jumlah hari dipastikan sistem saat
                        pengajuan dikirim.
                    </p>
                    <p>
                        Satu pengajuan dalam satu tahun, maksimal rentang 60 hari. Cuti tahunan
                        mulai hari ini; sakit/izin dapat diajukan sampai 7 hari ke belakang.
                    </p>
                    <p>
                        Atasan meninjau terlebih dahulu, lalu HR memberi keputusan akhir. Jika
                        atasan belum ditetapkan, pengajuan langsung ke HR.
                    </p>
                    <details>
                        <summary>Kalender libur kantor</summary>
                        {p.holidays?.length ? (
                            <ul>
                                {p.holidays.map((h) => (
                                    <li key={h.date}>
                                        {date(h.date)} · {h.name}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p>HR belum menambahkan hari libur.</p>
                        )}
                    </details>
                </div>
            </aside>
        </div>
    );
}
function Decision({ l, actions, isHr }: { l: Leave; actions: string[]; isHr: boolean }) {
    const f = useForm({ version: l.version, action: actions[0] ?? '', reason: '', confirm: false });
    return (
        <form
            className="ep-card"
            onSubmit={(e) => {
                e.preventDefault();
                if (f.data.confirm) f.post(`/leave/${l.id}/action`);
            }}
        >
            <h2>Tindak lanjut</h2>
            <Errors errors={f.errors} />
            <Field label="Keputusan">
                <select value={f.data.action} onChange={(e) => f.setData('action', e.target.value)}>
                    {actions.map((a) => (
                        <option key={a} value={a}>
                            {a === 'cancel' && l.status === 'approved'
                                ? isHr
                                    ? 'Batalkan pengajuan'
                                    : 'Ajukan pembatalan'
                                : actionLabels[a]}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Alasan keputusan">
                <textarea
                    rows={3}
                    required
                    minLength={10}
                    maxLength={2000}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <label className="lv-confirm">
                <input
                    type="checkbox"
                    checked={f.data.confirm}
                    onChange={(e) => f.setData('confirm', e.target.checked)}
                    required
                />
                Saya sudah memeriksa tanggal dan dampaknya pada saldo.
            </label>
            <button className="ep-primary" disabled={f.processing || !f.data.confirm}>
                {f.processing ? 'Menyimpan…' : 'Simpan keputusan'}
            </button>
            <p className="tr-caption">
                Pembatalan cuti yang sudah disetujui memerlukan keputusan HR sebelum tanggal mulai.
                Seluruh riwayat tetap disimpan.
            </p>
        </form>
    );
}
function Allocation({ employee, year }: { employee: Person; year: number }) {
    const [open, setOpen] = useState(false);
    const b = employee.balance;
    const f = useForm({
        employee_id: employee.id,
        year,
        days: b.allocated,
        version: b.version,
        reason: '',
    });
    return (
        <div className="lv-allocation">
            <div className="lv-allocation-head">
                <div>
                    <strong>{employee.full_name}</strong>
                    <small>{employee.employee_number}</small>
                </div>
                <div>
                    <strong>{b.allocated - b.used - b.reserved} hari tersedia</strong>
                    <small>
                        {b.allocated} alokasi · {b.reserved} tertahan · {b.used} terpakai
                    </small>
                </div>
                <button
                    type="button"
                    className="ep-secondary"
                    aria-expanded={open}
                    onClick={() => setOpen(!open)}
                >
                    Atur saldo
                </button>
            </div>
            {open && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post('/leave/settings/balances', { preserveScroll: true });
                    }}
                >
                    <Errors errors={f.errors} />
                    <div className="tr-form-grid">
                        <Field label={`Alokasi total ${year} (hari)`}>
                            <input
                                type="number"
                                min={b.used + b.reserved}
                                max={366}
                                required
                                value={f.data.days}
                                onChange={(e) => f.setData('days', Number(e.target.value))}
                            />
                        </Field>
                        <Field label="Alasan penetapan">
                            <input
                                required
                                minLength={10}
                                maxLength={2000}
                                value={f.data.reason}
                                onChange={(e) => f.setData('reason', e.target.value)}
                            />
                        </Field>
                    </div>
                    <button className="ep-primary" disabled={f.processing}>
                        {f.processing ? 'Menyimpan…' : 'Simpan alokasi'}
                    </button>
                </form>
            )}
        </div>
    );
}
function HolidayForm() {
    const f = useForm({ date: '', name: '', reason: '' });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                f.post('/leave/settings/holidays', {
                    preserveScroll: true,
                    onSuccess: () => f.reset(),
                });
            }}
        >
            <Errors errors={f.errors} />
            <Field label="Tanggal libur">
                <input
                    type="date"
                    required
                    value={f.data.date}
                    onChange={(e) => f.setData('date', e.target.value)}
                />
            </Field>
            <Field label="Nama hari libur">
                <input
                    required
                    maxLength={150}
                    value={f.data.name}
                    onChange={(e) => f.setData('name', e.target.value)}
                />
            </Field>
            <Field label="Alasan penetapan">
                <input
                    required
                    minLength={10}
                    maxLength={2000}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <button className="ep-secondary" disabled={f.processing}>
                Tambah hari libur
            </button>
        </form>
    );
}
function RemoveHoliday({ h }: { h: { id?: number; date: string; name: string } }) {
    const [open, setOpen] = useState(false);
    const f = useForm({ reason: '' });
    return (
        <li>
            <div>
                {date(h.date)} · {h.name}{' '}
                <button
                    className="lv-text-button"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                >
                    Hapus
                </button>
            </div>
            {open && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(`/leave/settings/holidays/${h.id}/remove`, { preserveScroll: true });
                    }}
                >
                    <Errors errors={f.errors} />
                    <Field label="Alasan menghapus hari libur">
                        <input
                            required
                            minLength={10}
                            maxLength={2000}
                            value={f.data.reason}
                            onChange={(e) => f.setData('reason', e.target.value)}
                        />
                    </Field>
                    <button className="ep-secondary" disabled={f.processing}>
                        Konfirmasi hapus hari libur
                    </button>
                </form>
            )}
        </li>
    );
}
export default function Leaves(p: Props) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [year, setYear] = useState(String(p.filters?.year ?? new Date().getFullYear()));
    const [status, setStatus] = useState(p.filters?.status ?? '');
    const title =
        p.mode === 'create'
            ? 'Ajukan cuti & izin'
            : p.mode === 'show'
              ? 'Detail pengajuan'
              : p.mode === 'settings'
                ? 'Pengaturan cuti'
                : p.isTeam
                  ? 'Persetujuan tim'
                  : p.isHr
                    ? 'Cuti & izin'
                    : 'Cuti & izin saya';
    const l = p.leave;
    const wrap = (children: ReactNode) =>
        p.isHr ? (
            <HrShell title={title} active="leave" user={p.auth.user}>
                {children}
            </HrShell>
        ) : (
            <PortalShell title={title} user={p.auth.user} active={p.isTeam ? 'approvals' : 'leave'}>
                {children}
            </PortalShell>
        );
    return wrap(
        <main id="hr-main" className="hr-main ep-main lv-page">
            {p.mode !== 'index' && (
                <Link className="ep-back" href={p.isTeam ? '/leave?scope=team' : '/leave'}>
                    <ArrowLeft size={14} />
                    Kembali ke cuti & izin
                </Link>
            )}
            <div className="hr-page-heading">
                <div>
                    <h1>{title}</h1>
                    <p>
                        {p.mode === 'settings'
                            ? 'Tetapkan alokasi tahunan dan kalender libur kantor.'
                            : p.mode === 'create'
                              ? 'Pilih tanggal, lengkapi alasan, lalu kirim untuk ditinjau.'
                              : p.mode === 'show'
                                ? 'Tanggal, keputusan, dan riwayat dalam satu pengajuan.'
                                : p.isHr || p.isTeam
                                  ? 'Tinjau pengajuan dan pastikan setiap keputusan tercatat.'
                                  : 'Periksa saldo dan ikuti perkembangan pengajuan Anda.'}
                    </p>
                </div>
                <div className="tr-actions">
                    {p.mode === 'index' &&
                        (p.isHr ? (
                            <Link className="ep-secondary" href="/leave/settings">
                                <Settings2 size={15} />
                                Pengaturan
                            </Link>
                        ) : (
                            !p.isTeam && (
                                <Link className="ep-primary" href="/leave/create">
                                    <Plus size={15} />
                                    Ajukan cuti / izin
                                </Link>
                            )
                        ))}
                </div>
            </div>
            {p.errors?.version && <Errors errors={{ version: p.errors.version }} />}
            {p.flash?.success && (
                <p className="ep-success" role="status">
                    {p.flash.success}
                </p>
            )}
            {p.mode === 'create' && <ApplicationForm p={p} />}
            {p.mode === 'index' && (
                <>
                    {p.balance && (
                        <>
                            <Metrics b={p.balance} />
                            <p className="tr-caption lv-caption">
                                Tertahan: menunggu keputusan atau pembatalan. Terpakai: sudah
                                disetujui. Sakit dan izin tidak mengurangi saldo cuti tahunan.
                            </p>
                        </>
                    )}
                    <form
                        className="tr-list-filters"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/leave', {
                                q,
                                year,
                                status,
                                ...(p.isTeam ? { scope: 'team' } : {}),
                            });
                        }}
                    >
                        <input
                            aria-label="Cari pengajuan pegawai"
                            placeholder="Nama atau nomor pegawai"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <input
                            aria-label="Tahun pengajuan"
                            type="number"
                            min={2000}
                            max={2100}
                            value={year}
                            onChange={(e) => setYear(e.target.value)}
                        />
                        <select
                            aria-label="Status pengajuan"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">Semua status</option>
                            {Object.entries(p.statuses ?? {}).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                        <button className="ep-secondary">Terapkan</button>
                    </form>
                    <section className="hr-panel">
                        <div className="tr-panel-heading">
                            <h2>
                                {p.requests?.total ?? 0} pengajuan · {p.filters?.year}
                            </h2>
                        </div>
                        <div className="hr-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Pegawai / pengajuan</th>
                                        <th>Tanggal</th>
                                        <th className="lv-number">Hari kerja</th>
                                        <th>Status</th>
                                        <th>
                                            <span className="sr-only">Detail</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {p.requests?.data.map((l) => (
                                        <tr key={l.id}>
                                            <td>
                                                <strong>{l.employee.full_name}</strong>
                                                <small className="tr-sub">
                                                    {l.type_label} · #{l.id}
                                                </small>
                                            </td>
                                            <td>
                                                {date(l.starts_on)}
                                                <small className="tr-sub">
                                                    s.d. {date(l.ends_on)}
                                                </small>
                                            </td>
                                            <td className="lv-number">{l.days}</td>
                                            <td>
                                                <Status leave={l} />
                                            </td>
                                            <td>
                                                <Link
                                                    href={`/leave/${l.id}`}
                                                    aria-label={`Detail pengajuan ${l.employee.full_name} nomor ${l.id}`}
                                                >
                                                    <ArrowRight size={17} />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!p.requests?.data.length && (
                            <p className="hr-empty">
                                Belum ada pengajuan yang sesuai. Pengajuan baru akan muncul di sini.
                            </p>
                        )}
                    </section>
                    {p.requests && <RecordPagination {...p.requests} />}
                </>
            )}
            {p.mode === 'show' && l && (
                <div className="lv-detail-layout">
                    <div>
                        <section className="ep-card">
                            <div className="lv-detail-title">
                                <div>
                                    <h2>{l.employee.full_name}</h2>
                                    <p className="tr-caption">
                                        {l.employee.employee_number} · Pengajuan #{l.id}
                                    </p>
                                </div>
                                <Status leave={l} />
                            </div>
                            <dl className="lv-details">
                                <div>
                                    <dt>Jenis</dt>
                                    <dd>{l.type_label}</dd>
                                </div>
                                <div>
                                    <dt>Jumlah hari kerja</dt>
                                    <dd>{l.days} hari</dd>
                                </div>
                                <div>
                                    <dt>Mulai</dt>
                                    <dd>{date(l.starts_on)}</dd>
                                </div>
                                <div>
                                    <dt>Sampai</dt>
                                    <dd>{date(l.ends_on)}</dd>
                                </div>
                            </dl>
                            <h3>Alasan pengajuan</h3>
                            <p className="lv-reason">{l.reason}</p>
                            <details>
                                <summary>Tanggal yang dihitung ({l.days} hari)</summary>
                                <ul className="lv-dates">
                                    {l.work_dates?.map((d) => (
                                        <li key={d}>{date(d)}</li>
                                    ))}
                                </ul>
                                <p className="tr-caption">
                                    Perhitungan disimpan saat pengajuan dibuat; perubahan hari libur
                                    berikutnya tidak mengubahnya.
                                </p>
                            </details>
                            {l.has_document && (
                                <a className="ep-secondary" href={`/leave/${l.id}/document`}>
                                    <Download size={15} />
                                    Unduh bukti PDF
                                </a>
                            )}
                        </section>
                        <History events={p.events ?? []} />
                    </div>
                    <aside>
                        {p.actions?.length ? (
                            <Decision
                                key={`${l.id}-${l.version}`}
                                l={l}
                                actions={p.actions}
                                isHr={p.isHr}
                            />
                        ) : (
                            <section className="ep-card">
                                <h2>Status saat ini</h2>
                                <p className="tr-caption">
                                    {l.status === 'pending_supervisor'
                                        ? 'Menunggu tinjauan atasan langsung. HR melanjutkan setelah persetujuan atasan.'
                                        : l.status === 'pending_hr'
                                          ? 'Menunggu keputusan akhir HR.'
                                          : l.status === 'cancel_pending'
                                            ? 'Menunggu keputusan pembatalan dari HR. Saldo dan tanggal masih ditahan.'
                                            : 'Tidak ada tindak lanjut yang tersedia untuk akun Anda.'}
                                </p>
                            </section>
                        )}
                    </aside>
                </div>
            )}
            {p.mode === 'settings' && (
                <>
                    <form
                        className="tr-search"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/leave/settings', { year, q });
                        }}
                    >
                        <input
                            aria-label="Cari pegawai untuk saldo"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Nama atau nomor pegawai"
                        />
                        <input
                            aria-label="Tahun alokasi"
                            type="number"
                            min={2000}
                            max={2100}
                            value={year}
                            onChange={(e) => setYear(e.target.value)}
                        />
                        <button className="ep-secondary">Terapkan</button>
                    </form>
                    <div className="lv-settings-layout">
                        <section className="ep-card">
                            <h2>Alokasi cuti tahunan · {p.filters?.year}</h2>
                            <p className="tr-caption">
                                Diisi HR sesuai kebijakan perusahaan, tanpa saldo otomatis. Tertahan
                                dan terpakai tidak boleh melebihi alokasi.
                            </p>
                            {p.employees?.data.map((e) => (
                                <Allocation
                                    key={`${e.id}-${e.balance.year}-${e.balance.version}`}
                                    employee={e}
                                    year={p.filters!.year}
                                />
                            ))}
                            {!p.employees?.data.length && (
                                <p className="hr-empty">Belum ada pegawai aktif yang cocok.</p>
                            )}
                            {p.employees && <RecordPagination {...p.employees} />}
                        </section>
                        <aside className="ep-card">
                            <h2>Hari libur kantor</h2>
                            <p className="tr-caption">
                                Kalender Senin–Jumat. Perubahan hanya berlaku untuk pengajuan baru.
                                Tambahkan tanggal resmi sesuai kalender kantor.
                            </p>
                            <ul className="lv-holidays">
                                {p.holidays?.map((h) => (
                                    <RemoveHoliday key={h.id} h={h} />
                                ))}
                            </ul>
                            {!p.holidays?.length && (
                                <p className="tr-caption">Belum ada hari libur pada tahun ini.</p>
                            )}
                            <HolidayForm />
                        </aside>
                    </div>
                    <History events={p.settingEvents ?? []} />
                </>
            )}
        </main>,
    );
}
