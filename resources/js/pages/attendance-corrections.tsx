import { Link, router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import HrShell, { type HrUser } from '../components/hr-shell';
import PortalShell from '../components/portal-shell';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import { attendanceDate } from '../components/attendance-format';
import type { AttendanceRecord } from '../types/attendance';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/attendance.css';
import '../css/attendance-corrections.css';

type Day = AttendanceRecord & { date: string };
type Correction = {
    id: number;
    version: number;
    day_version: number;
    status: string;
    status_label: string;
    date: string;
    reason: string;
    original: AttendanceRecord;
    clock_in: string;
    clock_out: string;
    employee: { id: number; full_name: string; employee_number: string };
};
type Props = {
    auth: { user: HrUser };
    mode: 'index' | 'create' | 'show';
    isHr: boolean;
    isTeam?: boolean;
    day?: Day;
    eligible?: boolean;
    message?: string | null;
    pending?: number | null;
    correction?: Correction;
    current?: AttendanceRecord;
    actions?: string[];
    events?: {
        id: number;
        action: string;
        after: string;
        actor: string;
        reason: string;
        created_at: string;
    }[];
    requests?: {
        data: Correction[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters?: { scope?: string; status?: string };
    statuses?: Record<string, string>;
    errors?: Record<string, string>;
    flash?: { success?: string };
};
const time = (v: string | null) =>
    v ? `${attendanceDate(v.slice(0, 10))} · ${v.slice(11, 16)} WIB` : 'Belum dicatat';
const actions: Record<string, string> = {
    approve: 'Setujui',
    reject: 'Tolak pengajuan',
    withdraw: 'Tarik pengajuan',
};

function Comparison({
    original,
    proposed,
}: {
    original: AttendanceRecord;
    proposed: { clock_in: string; clock_out: string };
}) {
    return (
        <div className="ac-compare">
            <div>
                <h3>Catatan saat pengajuan</h3>
                <dl>
                    <dt>Masuk</dt>
                    <dd>{time(original.clock_in)}</dd>
                    <dt>Pulang</dt>
                    <dd>{time(original.clock_out)}</dd>
                </dl>
            </div>
            <div>
                <h3>Usulan koreksi</h3>
                <dl>
                    <dt>Masuk</dt>
                    <dd>{time(proposed.clock_in)}</dd>
                    <dt>Pulang</dt>
                    <dd>{time(proposed.clock_out)}</dd>
                </dl>
            </div>
        </div>
    );
}

function SubmitForm({ day }: { day: Day }) {
    const f = useForm({
        day_id: day.id,
        day_version: day.version,
        clock_in: day.clock_in ?? `${day.date}T08:00`,
        clock_out: day.clock_out ?? `${day.date}T17:00`,
        reason: '',
        confirm: false,
    });
    return (
        <form
            className="ep-card at-form"
            onSubmit={(e) => {
                e.preventDefault();
                if (f.data.confirm) f.post('/attendance/corrections');
            }}
        >
            <h2>Usulkan waktu yang benar</h2>
            <p className="tr-caption">
                Jam diisi dalam WIB. Jadwal tetap; perubahan absensi berlaku setelah keputusan akhir
                HR.
            </p>
            <Comparison original={day} proposed={f.data} />
            <div className="tr-form-grid">
                <Field label="Usulan masuk (WIB)" error={f.errors.clock_in}>
                    <input
                        type="datetime-local"
                        required
                        value={f.data.clock_in}
                        onChange={(e) => f.setData('clock_in', e.target.value)}
                    />
                </Field>
                <Field label="Usulan pulang (WIB)" error={f.errors.clock_out}>
                    <input
                        type="datetime-local"
                        required
                        value={f.data.clock_out}
                        onChange={(e) => f.setData('clock_out', e.target.value)}
                    />
                </Field>
            </div>
            <Field label="Alasan koreksi" error={f.errors.reason}>
                <textarea
                    required
                    minLength={10}
                    maxLength={2000}
                    rows={4}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <label className="ac-check">
                <input
                    type="checkbox"
                    checked={f.data.confirm}
                    onChange={(e) => f.setData('confirm', e.target.checked)}
                />
                Saya sudah memeriksa tanggal, jam, dan alasan pengajuan.
            </label>
            <button className="ep-primary" disabled={f.processing || !f.data.confirm}>
                {f.processing ? 'Menyimpan…' : 'Kirim pengajuan'}
            </button>
        </form>
    );
}

function Decision({ c, available, hr }: { c: Correction; available: string[]; hr: boolean }) {
    const f = useForm({ version: c.version, action: available[0], reason: '', confirm: false });
    const approve = hr ? 'Setujui & terapkan koreksi' : 'Teruskan ke HR';
    return (
        <form
            className="ep-card at-form"
            onSubmit={(e) => {
                e.preventDefault();
                if (f.data.confirm)
                    f.post(`/attendance/corrections/${c.id}/decision`, { preserveScroll: true });
            }}
        >
            <h2>Keputusan pengajuan</h2>
            <Field label="Tindakan">
                <select value={f.data.action} onChange={(e) => f.setData('action', e.target.value)}>
                    {available.map((a) => (
                        <option key={a} value={a}>
                            {a === 'approve' ? approve : actions[a]}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Alasan keputusan" error={f.errors.reason}>
                <textarea
                    required
                    rows={3}
                    minLength={10}
                    maxLength={2000}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <label className="ac-check">
                <input
                    type="checkbox"
                    checked={f.data.confirm}
                    onChange={(e) => f.setData('confirm', e.target.checked)}
                />
                Saya sudah memeriksa usulan dan catatan terkait.
            </label>
            <button className="ep-primary" disabled={f.processing || !f.data.confirm}>
                {f.processing ? 'Menyimpan…' : 'Simpan keputusan'}
            </button>
        </form>
    );
}

export default function AttendanceCorrections(p: Props) {
    const c = p.correction;
    const wrap = (children: ReactNode) =>
        p.isHr ? (
            <HrShell title="Koreksi absensi" active="attendance" user={p.auth.user}>
                {children}
            </HrShell>
        ) : (
            <PortalShell title="Koreksi absensi" active="attendance" user={p.auth.user}>
                {children}
            </PortalShell>
        );
    return wrap(
        <main id="hr-main" className="hr-main ep-main at-page ac-page">
            <Link
                className="ep-back"
                href={p.mode === 'index' ? '/attendance' : '/attendance/corrections'}
            >
                {p.mode === 'index' ? 'Kembali ke kehadiran' : 'Kembali ke daftar koreksi'}
            </Link>
            <div className="hr-page-heading">
                <div>
                    <h1>
                        {p.mode === 'create'
                            ? 'Ajukan koreksi absensi'
                            : c
                              ? `Pengajuan koreksi #${c.id}`
                              : 'Koreksi absensi'}
                    </h1>
                    <p>
                        {c
                            ? `${c.employee.full_name} · ${attendanceDate(c.date)}`
                            : 'Periksa waktu, jelaskan perbedaannya, ikuti keputusan.'}
                    </p>
                </div>
            </div>
            {p.flash?.success && (
                <p className="at-success" role="status">
                    {p.flash.success}
                </p>
            )}
            {p.errors && Object.keys(p.errors).length > 0 && (
                <div className="ep-alert" role="alert">
                    {Object.entries(p.errors).map(([k, v]) => (
                        <p key={k}>{v}</p>
                    ))}
                </div>
            )}
            {p.mode === 'index' && (
                <>
                    {!p.isHr && p.auth.user.role === 'supervisor' && (
                        <nav className="ac-tabs" aria-label="Lingkup koreksi">
                            <Link
                                href="/attendance/corrections"
                                aria-current={!p.isTeam ? 'page' : undefined}
                            >
                                Pengajuan saya
                            </Link>
                            <Link
                                href="/attendance/corrections?scope=team"
                                aria-current={p.isTeam ? 'page' : undefined}
                            >
                                Tinjauan tim
                            </Link>
                        </nav>
                    )}
                    <div className="tr-search">
                        <Field label="Status pengajuan">
                            <select
                                value={p.filters?.status ?? ''}
                                onChange={(e) =>
                                    router.get('/attendance/corrections', {
                                        scope: p.filters?.scope ?? 'own',
                                        status: e.target.value,
                                    })
                                }
                            >
                                <option value="">Semua status</option>
                                {Object.entries(p.statuses ?? {}).map(([k, v]) => (
                                    <option value={k} key={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <section className="hr-panel">
                        <div className="tr-panel-heading">
                            <h2>{p.requests?.total ?? 0} pengajuan</h2>
                        </div>
                        <div className="hr-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Pegawai</th>
                                        <th>Tanggal kerja</th>
                                        <th>Status</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {p.requests?.data.map((r) => (
                                        <tr key={r.id}>
                                            <td>
                                                {r.employee.full_name}
                                                <small className="ep-cell-sub">
                                                    {r.employee.employee_number}
                                                </small>
                                            </td>
                                            <td>{attendanceDate(r.date)}</td>
                                            <td>
                                                <span className={`ac-status ac-${r.status}`}>
                                                    {r.status_label}
                                                </span>
                                            </td>
                                            <td>
                                                <Link href={`/attendance/corrections/${r.id}`}>
                                                    Periksa pengajuan
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!p.requests?.data.length && (
                            <p className="hr-empty">
                                Belum ada pengajuan pada lingkup ini. Pengajuan baru dibuat dari
                                detail tanggal di kalender kehadiran.
                            </p>
                        )}
                    </section>
                    {p.requests && <RecordPagination {...p.requests} />}
                </>
            )}
            {p.day && (
                <>
                    <p className="at-caption">
                        Tanggal kerja {attendanceDate(p.day.date)} · Jadwal{' '}
                        {time(p.day.scheduled_start)} — {time(p.day.scheduled_end)}
                    </p>
                    {p.pending ? (
                        <p className="at-notice">
                            Masih ada pengajuan untuk tanggal ini.{' '}
                            <Link href={`/attendance/corrections/${p.pending}`}>
                                Lihat pengajuan
                            </Link>
                        </p>
                    ) : p.eligible ? (
                        <SubmitForm key={`${p.day.id}-${p.day.version}`} day={p.day} />
                    ) : (
                        <p className="at-notice">{p.message}</p>
                    )}
                </>
            )}
            {c && (
                <>
                    <p>
                        <span className={`ac-status ac-${c.status}`}>{c.status_label}</span>
                    </p>
                    {p.current &&
                        p.current.version !== c.day_version &&
                        c.status !== 'approved' && (
                            <p className="at-notice">
                                Catatan absensi berubah sejak pengajuan. Persetujuan diblokir;
                                pengajuan dapat ditolak atau ditarik untuk diperiksa ulang.
                            </p>
                        )}
                    <div className="at-detail-grid">
                        <section className="ep-card at-detail">
                            <h2>Perbandingan waktu</h2>
                            <Comparison original={c.original} proposed={c} />
                            <h3>Alasan pengajuan</h3>
                            <p className="ac-reason">{c.reason}</p>
                            <div className="at-history">
                                <h3>Riwayat pengajuan</h3>
                                <ol>
                                    {p.events?.map((e) => (
                                        <li key={e.id}>
                                            <strong>
                                                {e.action === 'submitted'
                                                    ? 'Diajukan'
                                                    : e.action === 'approve'
                                                      ? e.after === 'approved'
                                                          ? 'Koreksi disetujui dan diterapkan'
                                                          : 'Diteruskan ke HR'
                                                      : e.action === 'reject'
                                                        ? 'Ditolak'
                                                        : 'Ditarik'}{' '}
                                                · {e.actor}
                                            </strong>
                                            <time>
                                                {new Date(e.created_at).toLocaleString('id-ID', {
                                                    timeZone: 'Asia/Jakarta',
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}{' '}
                                                WIB
                                            </time>
                                            <p>{e.reason}</p>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </section>
                        {!!p.actions?.length && (
                            <aside>
                                <Decision
                                    key={`${c.id}-${c.version}`}
                                    c={c}
                                    available={p.actions}
                                    hr={p.isHr}
                                />
                            </aside>
                        )}
                    </div>
                </>
            )}
        </main>,
    );
}
