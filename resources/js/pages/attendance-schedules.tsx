import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Search, X } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field } from '../components/record-fields';
import { attendanceDate } from '../components/attendance-format';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/attendance-schedules.css';

type Person = { id: number; full_name: string; employee_number: string; department?: string };
type Input = {
    employee_ids: number[];
    starts_on: string;
    ends_on: string;
    weekdays: number[];
    starts_at: string;
    ends_at: string;
    ends_next_day: boolean;
    skip_holidays: boolean;
    tolerance_minutes: number;
    reason: string;
};
type Preview = {
    token: string;
    expires_at: string;
    input: Input;
    employees: Person[];
    summary: { total: number; create: number; skip: number };
    rows: {
        employee_id: number;
        employee_name: string;
        employee_number: string;
        date: string;
        starts_at: string;
        ends_at: string;
        code: string;
        label: string;
    }[];
};
type Props = {
    auth: { user: HrUser };
    today: string;
    filters: { q?: string };
    employees: Person[];
    employeeCount: number;
    preview?: Preview | null;
    errors?: Record<string, string>;
};
const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const signature = (d: Input) =>
    JSON.stringify([
        d.employee_ids,
        d.starts_on,
        d.ends_on,
        d.weekdays,
        d.starts_at,
        d.ends_at,
        d.ends_next_day,
        d.skip_holidays,
        d.tolerance_minutes,
        d.reason.trim(),
    ]);
function Commit({ preview: p, current }: { preview: Preview; current: boolean }) {
    const f = useForm({ token: p.token, confirm: false });
    const [expired, setExpired] = useState(false);
    useEffect(() => {
        const remaining = new Date(p.expires_at).getTime() - Date.now();
        const timer = window.setTimeout(() => setExpired(true), Math.max(0, remaining));
        return () => window.clearTimeout(timer);
    }, [p.expires_at]);
    return (
        <form
            className="sc-commit"
            onSubmit={(e) => {
                e.preventDefault();
                if (current && !expired && f.data.confirm) f.post('/attendance/schedules');
            }}
        >
            <p>
                Pratinjau berlaku sampai{' '}
                {new Date(p.expires_at).toLocaleString('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    dateStyle: 'medium',
                    timeStyle: 'short',
                })}{' '}
                WIB. Sistem memeriksa ulang seluruh data sebelum menyimpan.
            </p>
            {!current && (
                <p className="sc-warning" role="status">
                    Isian berubah. Buat pratinjau baru sebelum menyimpan.
                </p>
            )}
            {expired && (
                <p className="sc-warning" role="status">
                    Pratinjau kedaluwarsa. Buat pratinjau baru.
                </p>
            )}
            <label className="sc-check">
                <input
                    type="checkbox"
                    checked={f.data.confirm}
                    onChange={(e) => f.setData('confirm', e.target.checked)}
                    disabled={!current || expired || p.summary.create === 0}
                    required
                />
                Saya sudah memeriksa pegawai, tanggal, dan jadwal yang akan dibuat.
            </label>
            <button
                className="ep-primary"
                disabled={
                    f.processing || !current || expired || !f.data.confirm || p.summary.create === 0
                }
            >
                {f.processing
                    ? 'Menyimpan seluruh jadwal…'
                    : `Simpan ${p.summary.create} jadwal baru`}
            </button>
        </form>
    );
}
function Review({ preview: p, current }: { preview: Preview; current: boolean }) {
    const [page, setPage] = useState(1);
    const pages = Math.ceil(p.rows.length / 25);
    return (
        <section className="ep-card sc-review" aria-labelledby="schedule-review-title">
            <div className="sc-section-heading">
                <h2 id="schedule-review-title">Periksa jadwal sebelum disimpan</h2>
                <span>Waktu WIB</span>
            </div>
            <div className="sc-summary">
                {[
                    ['Jadwal diperiksa', p.summary.total],
                    ['Jadwal baru', p.summary.create],
                    ['Dilewati', p.summary.skip],
                ].map(([label, value]) => (
                    <div key={label}>
                        <span>{label}</span>
                        <strong>{value}</strong>
                    </div>
                ))}
            </div>
            <p className="tr-caption">
                Catatan lama tidak ditimpa, termasuk catatan yang dibatalkan. Buka detail pegawai
                untuk koreksi. Tidak ada jam hadir yang dibuat dari penjadwalan ini.
            </p>
            <div className="hr-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Tanggal kerja</th>
                            <th>Jadwal</th>
                            <th>Hasil pemeriksaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {p.rows.slice((page - 1) * 25, page * 25).map((row) => (
                            <tr key={`${row.employee_id}-${row.date}`}>
                                <td>
                                    <strong>{row.employee_name}</strong>
                                    <small className="tr-sub">{row.employee_number}</small>
                                </td>
                                <td>{attendanceDate(row.date)}</td>
                                <td>
                                    {row.starts_at.slice(11)}–{row.ends_at.slice(11)}
                                    {row.ends_at.slice(0, 10) !== row.date && (
                                        <small className="tr-sub">
                                            Pulang {attendanceDate(row.ends_at.slice(0, 10))}
                                        </small>
                                    )}
                                </td>
                                <td>
                                    <span
                                        className={`sc-result ${row.code === 'create' ? 'sc-create' : 'sc-skip'}`}
                                    >
                                        {row.code === 'create' ? 'Akan dibuat' : 'Dilewati'}
                                    </span>
                                    <small className="tr-sub">{row.label}</small>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {pages > 1 && (
                <nav className="sc-pagination" aria-label="Halaman pratinjau">
                    <button
                        type="button"
                        className="ep-secondary"
                        disabled={page === 1}
                        onClick={() => setPage(page - 1)}
                    >
                        Sebelumnya
                    </button>
                    <span>
                        Halaman {page} / {pages} · {p.rows.length} baris
                    </span>
                    <button
                        type="button"
                        className="ep-secondary"
                        disabled={page === pages}
                        onClick={() => setPage(page + 1)}
                    >
                        Berikutnya
                    </button>
                </nav>
            )}
            <Commit key={p.token} preview={p} current={current} />
        </section>
    );
}
export default function AttendanceSchedules(p: Props) {
    const f = useForm<Input>(
        p.preview?.input ?? {
            employee_ids: [],
            starts_on: p.today,
            ends_on: p.today,
            weekdays: [1, 2, 3, 4, 5],
            starts_at: '08:00',
            ends_at: '17:00',
            ends_next_day: false,
            skip_holidays: true,
            tolerance_minutes: 0,
            reason: '',
        },
    );
    const [selected, setSelected] = useState<Record<number, Person>>(
        Object.fromEntries((p.preview?.employees ?? []).map((e) => [e.id, e])),
    );
    const [q, setQ] = useState(p.filters.q ?? '');
    const toggle = (e: Person) => {
        const active = f.data.employee_ids.includes(e.id);
        const ids = active
            ? f.data.employee_ids.filter((id) => id !== e.id)
            : [...f.data.employee_ids, e.id].sort((a, b) => a - b);
        f.setData('employee_ids', ids);
        setSelected((previous) => ({ ...previous, [e.id]: e }));
    };
    const all = Array.from(new Set([...f.data.employee_ids, ...p.employees.map((e) => e.id)])).sort(
        (a, b) => a - b,
    );
    const current = !!p.preview && signature(f.data) === signature(p.preview.input);
    return (
        <HrShell title="Jadwal kerja massal" active="attendance" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main sc-page">
                <Link className="ep-back" href="/attendance">
                    <ArrowLeft size={14} />
                    Kembali ke absensi
                </Link>
                <div className="hr-page-heading">
                    <div>
                        <h1>Jadwal kerja massal</h1>
                        <p>
                            Pilih pegawai, susun hari kerjanya, lalu periksa pratinjau sebelum
                            menyimpan.
                        </p>
                    </div>
                </div>
                {p.errors && Object.keys(p.errors).length > 0 && (
                    <div className="ep-alert" role="alert">
                        {Object.entries(p.errors).map(([k, v]) => (
                            <p key={k}>{v}</p>
                        ))}
                    </div>
                )}
                <div className="sc-layout">
                    <section className="ep-card sc-people">
                        <div className="sc-section-heading">
                            <h2>Pilih pegawai</h2>
                            <span>{f.data.employee_ids.length} / 50 dipilih</span>
                        </div>
                        <form
                            className="tr-search"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    '/attendance/schedules',
                                    { q },
                                    { preserveState: true, preserveScroll: true },
                                );
                            }}
                        >
                            <input
                                aria-label="Cari pegawai untuk jadwal"
                                placeholder="Nama atau nomor pegawai"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <button className="ep-secondary" aria-label="Cari pegawai">
                                <Search size={16} />
                            </button>
                        </form>
                        <div className="sc-people-actions">
                            <button
                                type="button"
                                className="sc-text-button"
                                disabled={all.length > 50 || !p.employees.length}
                                onClick={() => {
                                    f.setData('employee_ids', all);
                                    setSelected((previous) => ({
                                        ...previous,
                                        ...Object.fromEntries(p.employees.map((e) => [e.id, e])),
                                    }));
                                }}
                            >
                                Pilih hasil pencarian
                            </button>
                            <button
                                type="button"
                                className="sc-text-button"
                                disabled={!f.data.employee_ids.length}
                                onClick={() => f.setData('employee_ids', [])}
                            >
                                Kosongkan pilihan
                            </button>
                        </div>
                        <div className="sc-people-list">
                            {p.employees.map((e) => (
                                <label className="sc-person" key={e.id}>
                                    <input
                                        type="checkbox"
                                        checked={f.data.employee_ids.includes(e.id)}
                                        disabled={
                                            !f.data.employee_ids.includes(e.id) &&
                                            f.data.employee_ids.length >= 50
                                        }
                                        onChange={() => toggle(e)}
                                    />
                                    <span>
                                        <strong>{e.full_name}</strong>
                                        <small>
                                            {e.employee_number} · {e.department}
                                        </small>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {!p.employees.length && (
                            <p className="hr-empty">Tidak ada pegawai aktif yang cocok.</p>
                        )}
                        <p className="tr-caption">
                            Menampilkan {p.employees.length} dari {p.employeeCount} hasil. Gunakan
                            pencarian untuk pegawai lain; pilihan tetap tersimpan.
                        </p>
                        {!!f.data.employee_ids.length && (
                            <div className="sc-selection" aria-label="Pegawai terpilih">
                                {f.data.employee_ids.map(
                                    (id) =>
                                        selected[id] && (
                                            <button
                                                key={id}
                                                type="button"
                                                aria-label={`Hapus pilihan ${selected[id].full_name}`}
                                                onClick={() => toggle(selected[id])}
                                            >
                                                {selected[id].full_name}
                                                <X size={12} />
                                            </button>
                                        ),
                                )}
                            </div>
                        )}
                    </section>
                    <form
                        className="ep-card sc-pattern"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post('/attendance/schedules/preview', {
                                preserveScroll: true,
                                onSuccess: () => setQ(''),
                            });
                        }}
                    >
                        <h2>Atur hari & waktu</h2>
                        <div className="tr-form-grid">
                            <Field label="Tanggal mulai">
                                <input
                                    required
                                    type="date"
                                    min={p.today}
                                    value={f.data.starts_on}
                                    onChange={(e) => f.setData('starts_on', e.target.value)}
                                />
                            </Field>
                            <Field label="Tanggal akhir">
                                <input
                                    required
                                    type="date"
                                    min={f.data.starts_on}
                                    value={f.data.ends_on}
                                    onChange={(e) => f.setData('ends_on', e.target.value)}
                                />
                            </Field>
                        </div>
                        <fieldset className="sc-weekdays">
                            <legend>Hari yang dijadwalkan</legend>
                            {days.map((d, i) => (
                                <label key={d}>
                                    <input
                                        type="checkbox"
                                        checked={f.data.weekdays.includes(i + 1)}
                                        onChange={(e) =>
                                            f.setData(
                                                'weekdays',
                                                e.target.checked
                                                    ? [...f.data.weekdays, i + 1].sort(
                                                          (a, b) => a - b,
                                                      )
                                                    : f.data.weekdays.filter((v) => v !== i + 1),
                                            )
                                        }
                                    />
                                    {d}
                                </label>
                            ))}
                        </fieldset>
                        <div className="tr-form-grid">
                            <Field label="Jam mulai (WIB)">
                                <input
                                    required
                                    type="time"
                                    value={f.data.starts_at}
                                    onChange={(e) => f.setData('starts_at', e.target.value)}
                                />
                            </Field>
                            <Field label="Jam akhir (WIB)">
                                <input
                                    required
                                    type="time"
                                    value={f.data.ends_at}
                                    onChange={(e) => f.setData('ends_at', e.target.value)}
                                />
                            </Field>
                        </div>
                        <label className="sc-check">
                            <input
                                type="checkbox"
                                checked={f.data.ends_next_day}
                                onChange={(e) => f.setData('ends_next_day', e.target.checked)}
                            />
                            Jam akhir pada hari berikutnya (jadwal malam)
                        </label>
                        <Field label="Toleransi terlambat (menit)">
                            <input
                                required
                                type="number"
                                min={0}
                                max={120}
                                value={f.data.tolerance_minutes}
                                onChange={(e) =>
                                    f.setData('tolerance_minutes', Number(e.target.value))
                                }
                            />
                        </Field>
                        <label className="sc-check">
                            <input
                                type="checkbox"
                                checked={f.data.skip_holidays}
                                onChange={(e) => f.setData('skip_holidays', e.target.checked)}
                            />
                            Lewati hari libur kantor
                        </label>
                        <Field label="Alasan penjadwalan">
                            <textarea
                                required
                                minLength={10}
                                maxLength={2000}
                                rows={3}
                                value={f.data.reason}
                                onChange={(e) => f.setData('reason', e.target.value)}
                            />
                        </Field>
                        <p className="tr-caption">
                            Maksimal 31 hari per proses, sampai 90 hari ke depan, dan 1.000 pasangan
                            pegawai/tanggal. Catatan lama, cuti/izin disetujui, sebelum bergabung,
                            jadwal bertumpuk, dan jam mulai yang sudah lewat akan dilewati.
                        </p>
                        <button
                            className="ep-primary"
                            disabled={
                                f.processing ||
                                !f.data.employee_ids.length ||
                                !f.data.weekdays.length
                            }
                        >
                            {f.processing ? 'Memeriksa jadwal…' : 'Buat pratinjau'}
                        </button>
                    </form>
                </div>
                {p.preview && (
                    <Review key={p.preview.token} preview={p.preview} current={current} />
                )}
            </main>
        </HrShell>
    );
}
