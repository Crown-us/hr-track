import { Link, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import PortalShell from '../components/portal-shell';
import AttendanceCalendar from '../components/attendance-calendar';
import AttendancePunch from '../components/attendance-punch';
import { attendanceDate } from '../components/attendance-format';
import type { AttendanceCalendarData, AttendancePunchData } from '../types/attendance';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/attendance.css';

type Person = {
    id: number;
    full_name: string;
    employee_number: string;
    department?: string;
    position?: string;
    active?: boolean;
    join_date?: string;
};
type Props = {
    auth: { user: HrUser };
    mode: 'index' | 'show';
    isHr: boolean;
    isTeam?: boolean;
    baseUrl?: string;
    employee?: Person;
    calendar?: AttendanceCalendarData;
    punch?: AttendancePunchData | null;
    filters?: { q?: string };
    employees?: {
        data: Person[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    errors?: Record<string, string>;
    flash?: { success?: string };
};
const localTime = (v: string | null | undefined) =>
    v ? `${attendanceDate(v.slice(0, 10))} · ${v.slice(11, 16)} WIB` : '—';
function DayForm({ c, employee }: { c: AttendanceCalendarData; employee: Person }) {
    const d = c.selected;
    const r = d.record;
    const f = useForm({
        date: d.date,
        version: r?.version ?? 0,
        scheduled_start: r?.scheduled_start ?? `${d.date}T08:00`,
        scheduled_end: r?.scheduled_end ?? `${d.date}T17:00`,
        clock_in: r?.clock_in ?? '',
        clock_out: r?.clock_out ?? '',
        tolerance_minutes: r?.tolerance_minutes ?? 0,
        action: 'save',
        reason: '',
        confirm: false,
    });
    return (
        <form
            className="ep-card at-form"
            onSubmit={(e) => {
                e.preventDefault();
                if (f.data.confirm)
                    f.post(`/attendance/employees/${employee.id}/days`, { preserveScroll: true });
            }}
        >
            <h2>{r ? 'Koreksi catatan HR' : 'Catat jadwal & kehadiran'}</h2>
            <p className="tr-caption">
                Waktu diisi dalam WIB. Catatan manual disimpan bersama alasan dan riwayat perubahan.
            </p>
            {r?.voided && (
                <p className="at-notice">
                    Catatan ini dibatalkan. Menyimpan koreksi akan mengaktifkannya kembali.
                </p>
            )}
            {r && !r.voided && (
                <Field label="Tindakan">
                    <select
                        aria-label="Tindakan pencatatan"
                        value={f.data.action}
                        onChange={(e) => f.setData('action', e.target.value)}
                    >
                        <option value="save">Koreksi jadwal / waktu</option>
                        <option value="void">Batalkan catatan, simpan riwayat</option>
                    </select>
                </Field>
            )}
            {f.data.action === 'save' && (
                <>
                    <div className="tr-form-grid">
                        <Field label="Awal jadwal (WIB)">
                            <input
                                type="datetime-local"
                                required
                                value={f.data.scheduled_start}
                                onChange={(e) => f.setData('scheduled_start', e.target.value)}
                            />
                        </Field>
                        <Field label="Akhir jadwal (WIB)">
                            <input
                                type="datetime-local"
                                required
                                value={f.data.scheduled_end}
                                onChange={(e) => f.setData('scheduled_end', e.target.value)}
                            />
                        </Field>
                        <Field label="Jam masuk (WIB)">
                            <input
                                type="datetime-local"
                                value={f.data.clock_in}
                                onChange={(e) => f.setData('clock_in', e.target.value)}
                            />
                        </Field>
                        <Field label="Jam pulang (WIB)">
                            <input
                                type="datetime-local"
                                value={f.data.clock_out}
                                onChange={(e) => f.setData('clock_out', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label="Toleransi terlambat (menit)">
                        <input
                            type="number"
                            min={0}
                            max={120}
                            required
                            value={f.data.tolerance_minutes}
                            onChange={(e) => f.setData('tolerance_minutes', Number(e.target.value))}
                        />
                    </Field>
                    <p className="tr-caption">
                        Kosongkan masuk/pulang jika baru menyiapkan jadwal. Jadwal malam boleh
                        berakhir pada hari berikutnya. Default jam pada form belum menjadi jadwal
                        sampai disimpan.
                    </p>
                </>
            )}
            <Field label="Alasan pencatatan / koreksi">
                <textarea
                    required
                    minLength={10}
                    maxLength={2000}
                    rows={3}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <label className="at-confirm">
                <input
                    type="checkbox"
                    required
                    checked={f.data.confirm}
                    onChange={(e) => f.setData('confirm', e.target.checked)}
                />
                Saya sudah memeriksa tanggal, waktu, dan sumber catatan.
            </label>
            <button className="ep-primary" disabled={f.processing || !f.data.confirm}>
                {f.processing
                    ? 'Menyimpan…'
                    : f.data.action === 'void'
                      ? 'Konfirmasi pembatalan catatan'
                      : 'Simpan catatan'}
            </button>
        </form>
    );
}
export default function Attendance(p: Props) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [busy, setBusy] = useState(false);
    const c = p.calendar;
    const e = p.employee;
    const [year, setYear] = useState(String(c?.year ?? new Date().getFullYear()));
    const title =
        p.mode === 'index'
            ? 'Absensi pegawai'
            : p.isTeam
              ? 'Kehadiran anggota tim'
              : p.isHr
                ? 'Kehadiran pegawai'
                : 'Kehadiran saya';
    const visit = (y: number, date?: string) =>
        router.get(
            p.baseUrl!,
            { year: y, ...(date ? { date } : {}) },
            {
                preserveScroll: true,
                preserveState: y === c?.year,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    const wrap = (children: ReactNode) =>
        p.isHr ? (
            <HrShell title={title} active="attendance" user={p.auth.user}>
                {children}
            </HrShell>
        ) : (
            <PortalShell title={title} active={p.isTeam ? 'team' : 'attendance'} user={p.auth.user}>
                {children}
            </PortalShell>
        );
    return wrap(
        <main id="hr-main" className="hr-main ep-main at-page">
            {p.mode === 'show' && (p.isHr || p.isTeam) && (
                <Link className="ep-back" href={p.isHr ? '/attendance' : '/team'}>
                    <ArrowLeft size={14} />
                    {p.isHr ? 'Kembali ke absensi pegawai' : 'Kembali ke tim'}
                </Link>
            )}
            <div className="hr-page-heading">
                <div>
                    <h1>{title}</h1>
                    <p>
                        {e
                            ? `${e.full_name} · ${e.employee_number}`
                            : 'Pilih pegawai untuk melihat kalender dan catatan hariannya.'}
                    </p>
                </div>
                {p.mode === 'index' && (
                    <Link className="ep-primary" href="/attendance/schedules">
                        <Plus size={15} />
                        Jadwal massal
                    </Link>
                )}
                {c && (
                    <form
                        className="at-year"
                        onSubmit={(ev) => {
                            ev.preventDefault();
                            visit(Number(year));
                        }}
                    >
                        <button
                            type="button"
                            className="hr-icon"
                            aria-label="Tahun sebelumnya"
                            disabled={busy || c.year <= 2000}
                            onClick={() => visit(c.year - 1)}
                        >
                            <ChevronLeft size={17} />
                        </button>
                        <label>
                            <span className="sr-only">Tahun kalender</span>
                            <input
                                aria-label="Tahun kalender"
                                type="number"
                                min={2000}
                                max={2100}
                                required
                                value={year}
                                onChange={(ev) => setYear(ev.target.value)}
                            />
                        </label>
                        <button className="ep-secondary" disabled={busy}>
                            Tampilkan
                        </button>
                        <button
                            type="button"
                            className="hr-icon"
                            aria-label="Tahun berikutnya"
                            disabled={busy || c.year >= 2100}
                            onClick={() => visit(c.year + 1)}
                        >
                            <ChevronRight size={17} />
                        </button>
                    </form>
                )}
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
            <p>
                <Link className="ep-back" href="/attendance/corrections">
                    {p.isHr ? 'Periksa pengajuan koreksi absensi' : 'Pengajuan koreksi absensi'}
                </Link>
            </p>
            {p.isHr && (
                <p>
                    <Link className="ep-back" href="/attendance/recap">
                        Rekap absensi bulanan & ekspor
                    </Link>
                </p>
            )}
            {p.mode === 'index' && (
                <>
                    <form
                        className="tr-search"
                        onSubmit={(ev) => {
                            ev.preventDefault();
                            router.get('/attendance', { q });
                        }}
                    >
                        <input
                            aria-label="Cari pegawai absensi"
                            placeholder="Nama atau nomor pegawai"
                            value={q}
                            onChange={(ev) => setQ(ev.target.value)}
                        />
                        <button className="ep-secondary">Cari</button>
                    </form>
                    <section className="hr-panel">
                        <div className="tr-panel-heading">
                            <h2>{p.employees?.total ?? 0} pegawai</h2>
                        </div>
                        <div className="hr-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Pegawai</th>
                                        <th>Departemen</th>
                                        <th>Jabatan</th>
                                        <th>
                                            <span className="sr-only">Kalender kehadiran</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {p.employees?.data.map((e) => (
                                        <tr key={e.id}>
                                            <td>
                                                <strong>{e.full_name}</strong>
                                                <small className="tr-sub">
                                                    {e.employee_number}
                                                </small>
                                            </td>
                                            <td>{e.department}</td>
                                            <td>{e.position}</td>
                                            <td>
                                                <Link
                                                    aria-label={`Kalender ${e.full_name}`}
                                                    href={`/attendance/employees/${e.id}`}
                                                >
                                                    <ArrowRight size={17} />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!p.employees?.data.length && (
                            <p className="hr-empty">Belum ada pegawai yang cocok.</p>
                        )}
                    </section>
                    {p.employees && <RecordPagination {...p.employees} />}
                </>
            )}
            {c && e && (
                <>
                    {p.punch && <AttendancePunch data={p.punch} />}
                    <section className="at-summary" aria-label="Ringkasan tahun sampai hari ini">
                        {[
                            ['Hadir lengkap', c.summary.present],
                            ['Tepat waktu', c.summary.on_time],
                            ['Terlambat', c.summary.late],
                            ['Perlu diperiksa', c.summary.review],
                            ['Tanpa keterangan', c.summary.absent],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <span>{label}</span>
                                <strong>
                                    {value}
                                    <small> hari</small>
                                </strong>
                            </div>
                        ))}
                    </section>
                    <p className="at-caption">
                        Ringkasan {c.year}{' '}
                        {c.year > Number(c.today.slice(0, 4))
                            ? 'belum berjalan'
                            : c.year < Number(c.today.slice(0, 4))
                              ? 'sampai akhir tahun'
                              : `sampai ${attendanceDate(c.today)}`}
                        . Hadir lengkap = tepat waktu + terlambat. Tanggal tanpa data tidak dihitung
                        sebagai absen.
                    </p>
                    <AttendanceCalendar
                        key={c.year}
                        calendar={c}
                        onSelect={(d) => visit(c.year, d)}
                        busy={busy}
                    />
                    <div className="at-detail-layout">
                        <section className="ep-card at-detail" aria-live="polite">
                            <div className="at-detail-heading">
                                <div>
                                    <h2>{attendanceDate(c.selected.date)}</h2>
                                </div>
                                <span className={`at-status at-${c.selected.status}`}>
                                    <span
                                        className={`at-swatch at-${c.selected.status}`}
                                        aria-hidden="true"
                                    />
                                    {c.selected.label}
                                </span>
                            </div>
                            {c.selected.record && !c.selected.record.voided ? (
                                <dl className="at-facts">
                                    {[
                                        [
                                            'Awal jadwal',
                                            localTime(c.selected.record.scheduled_start),
                                        ],
                                        [
                                            'Akhir jadwal',
                                            localTime(c.selected.record.scheduled_end),
                                        ],
                                        ['Masuk', localTime(c.selected.record.clock_in)],
                                        ['Pulang', localTime(c.selected.record.clock_out)],
                                        [
                                            'Menit lewat awal jadwal',
                                            c.selected.record.clock_in
                                                ? `${c.selected.late_minutes} menit`
                                                : '—',
                                        ],
                                        [
                                            'Toleransi',
                                            `${c.selected.record.tolerance_minutes} menit`,
                                        ],
                                        ['Sumber', c.selected.record.source],
                                    ].map(([k, v]) => (
                                        <div key={k}>
                                            <dt>{k}</dt>
                                            <dd>{v}</dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : (
                                <p className="tr-caption">
                                    {c.selected.record?.voided
                                        ? 'Catatan pada tanggal ini dibatalkan. Riwayat tetap disimpan.'
                                        : 'Belum ada catatan jam masuk/pulang pada tanggal ini.'}
                                </p>
                            )}
                            {c.selected.leave && (
                                <p className="at-notice">
                                    {c.selected.leave.label} disetujui untuk tanggal ini.
                                    {c.selected.status === 'review'
                                        ? ' Ada catatan hadir yang perlu dicocokkan HR.'
                                        : ''}
                                </p>
                            )}
                            {c.selected.holiday && (
                                <p className="tr-caption">Libur kantor: {c.selected.holiday}</p>
                            )}
                            {!p.isHr &&
                                !p.isTeam &&
                                c.selected.record &&
                                !c.selected.record.voided && (
                                    <p>
                                        <Link
                                            className="ep-secondary"
                                            href={`/attendance/corrections/create?day_id=${c.selected.record.id}`}
                                        >
                                            Ajukan koreksi tanggal ini
                                        </Link>
                                    </p>
                                )}
                            {c.selected.status === 'absent' && (
                                <p className="at-notice">
                                    Jadwal sudah berakhir tanpa catatan masuk atau cuti/izin
                                    disetujui. HR perlu memastikan kelengkapan sumber absensi
                                    sebelum tindak lanjut.
                                </p>
                            )}
                            {c.events.length > 0 && (
                                <div className="at-history">
                                    <h3>Riwayat pencatatan</h3>
                                    <ol>
                                        {c.events.map((v) => (
                                            <li key={v.id}>
                                                <strong>
                                                    {v.action === 'recorded'
                                                        ? 'Dicatat'
                                                        : v.action === 'clocked_in'
                                                          ? 'Masuk melalui web'
                                                          : v.action === 'clocked_out'
                                                            ? 'Pulang melalui web'
                                                            : v.action === 'voided'
                                                              ? 'Catatan dibatalkan'
                                                              : 'Dikoreksi'}{' '}
                                                    · {v.actor}
                                                </strong>
                                                <time>
                                                    {new Date(v.created_at).toLocaleString(
                                                        'id-ID',
                                                        {
                                                            timeZone: 'Asia/Jakarta',
                                                            dateStyle: 'medium',
                                                            timeStyle: 'short',
                                                        },
                                                    )}{' '}
                                                    WIB
                                                </time>
                                                <p>{v.reason}</p>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            )}
                        </section>
                        <aside>
                            {p.isHr && e.active && c.selected.status !== 'not_employed' ? (
                                <DayForm
                                    key={`${e.id}-${c.selected.date}-${c.selected.record?.version ?? 0}`}
                                    c={c}
                                    employee={e}
                                />
                            ) : (
                                <div className="ep-card at-explanation">
                                    <CalendarDays size={20} />
                                    <h2>Catatan yang jelas</h2>
                                    <p>
                                        Gunakan panel Catat kehadiran untuk jadwal aktif milik
                                        sendiri. Hubungi HR jika jam atau status perlu diperbaiki.
                                    </p>
                                    <p>
                                        Merah hanya muncul pada jadwal yang sudah selesai. Abu-abu
                                        dan data kosong tidak berarti mangkir.
                                    </p>
                                </div>
                            )}
                        </aside>
                    </div>
                </>
            )}
        </main>,
    );
}
