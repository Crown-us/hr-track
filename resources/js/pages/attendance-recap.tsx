import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/attendance.css';
import '../css/attendance-recap.css';

type Counts = Record<string, number>;
type Filters = { month: string; department_id?: string | number; q?: string };
type Props = {
    auth: { user: HrUser };
    filters: Filters;
    columns: Record<string, string>;
    departments: { id: number; name: string }[];
    report: {
        month: string;
        total: number;
        as_of: string;
        generated_at: string;
        summary: Counts | null;
        limited?: boolean;
        filter_label: string;
    };
    employees: {
        data: {
            id: number;
            full_name: string;
            employee_number: string;
            department: string;
            status_label: string;
            archived: boolean;
            counts: Counts;
        }[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    errors?: Record<string, string>;
};
function FiltersForm({ p }: { p: Props }) {
    const [f, setF] = useState({
        month: p.filters.month,
        department_id: p.filters.department_id ?? '',
        q: p.filters.q ?? '',
    });
    return (
        <form
            className="ep-card ar-filters"
            onSubmit={(e) => {
                e.preventDefault();
                router.get('/attendance/recap', f);
            }}
        >
            <Field label="Bulan rekap" error={p.errors?.month}>
                <input
                    type="month"
                    min="2000-01"
                    max={p.report.as_of.slice(0, 7)}
                    required
                    value={f.month}
                    onChange={(e) => setF({ ...f, month: e.target.value })}
                />
            </Field>
            <Field label="Departemen" error={p.errors?.department_id}>
                <select
                    value={f.department_id}
                    onChange={(e) => setF({ ...f, department_id: e.target.value })}
                >
                    <option value="">Semua departemen</option>
                    {p.departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Cari pegawai" error={p.errors?.q}>
                <input
                    value={f.q}
                    maxLength={100}
                    placeholder="Nama atau nomor pegawai"
                    onChange={(e) => setF({ ...f, q: e.target.value })}
                />
            </Field>
            <button className="ep-primary">Terapkan filter</button>
        </form>
    );
}

export default function AttendanceRecap(p: Props) {
    const report = p.report;
    const s = report.summary;
    const columns = Object.entries(p.columns);
    const query = new URLSearchParams({ month: p.filters.month });
    if (p.filters.department_id) query.set('department_id', String(p.filters.department_id));
    if (p.filters.q) query.set('q', p.filters.q);
    const exportUrl = (format: string) =>
        `/attendance/recap/export?${query.toString()}&format=${format}`;
    return (
        <HrShell title="Rekap absensi bulanan" active="attendance" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main at-page ar-page">
                <Link className="ep-back" href="/attendance">
                    Kembali ke absensi pegawai
                </Link>
                <div className="hr-page-heading">
                    <div>
                        <h1>Rekap absensi bulanan</h1>
                        <p>Periksa kehadiran tim berdasarkan tanggal kerja dalam WIB.</p>
                    </div>
                    <div className="ar-exports">
                        {!report.limited && (
                            <a className="ep-secondary" href={exportUrl('xlsx')}>
                                <Download size={15} />
                                Excel
                            </a>
                        )}
                        {report.total <= 200 ? (
                            <a className="ep-secondary" href={exportUrl('pdf')}>
                                <Download size={15} />
                                PDF
                            </a>
                        ) : (
                            <button
                                className="ep-secondary"
                                disabled
                                title="Persempit filter hingga maksimal 200 pegawai"
                            >
                                PDF · maksimal 200 pegawai
                            </button>
                        )}
                    </div>
                </div>
                {p.errors && Object.keys(p.errors).length > 0 && (
                    <div className="ep-alert" role="alert">
                        {Object.entries(p.errors).map(([k, v]) => (
                            <p key={k}>{v}</p>
                        ))}
                    </div>
                )}
                <FiltersForm
                    key={`${p.filters.month}-${p.filters.department_id}-${p.filters.q}`}
                    p={p}
                />
                <p className="at-caption">
                    Hasil filter: {report.month} · {report.filter_label}. Dibuat{' '}
                    {new Date(report.generated_at).toLocaleString('id-ID', {
                        timeZone: 'Asia/Jakarta',
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })}{' '}
                    WIB. File ekspor memakai filter yang sudah diterapkan.
                </p>
                {report.limited && (
                    <p className="ep-alert" role="alert">
                        Ada {report.total} pegawai. Persempit departemen atau pencarian hingga
                        maksimal 1.000 pegawai untuk menghitung dan mengekspor rekap. Belum ada
                        angka yang dihitung.
                    </p>
                )}
                {s && (
                    <>
                        <section
                            className="at-summary ar-summary"
                            aria-label="Ringkasan seluruh hasil filter"
                        >
                            {[
                                ['Hadir lengkap', s.present],
                                ['Terlambat', s.late],
                                ['Cuti / izin / sakit', s.annual + s.permission + s.sick],
                                ['Tanpa keterangan', s.absent],
                                ['Perlu diperiksa', s.review],
                                ['Belum ada data', s.unknown],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <span>{label}</span>
                                    <strong>
                                        {value}
                                        <small> hari-pegawai</small>
                                    </strong>
                                </div>
                            ))}
                        </section>
                        <p className="at-help">
                            Ringkasan mencakup seluruh {report.total} pegawai sesuai filter, bukan
                            hanya halaman ini. Hadir lengkap = {s.on_time} tepat waktu + {s.late}{' '}
                            terlambat. Terlambat merupakan bagian dari hadir lengkap.
                        </p>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>
                                    {report.total} pegawai · {report.month}
                                </h2>
                                <span className="tr-caption">Angka dalam hari per pegawai</span>
                            </div>
                            <div className="hr-table-scroll">
                                <table className="ar-table">
                                    <thead>
                                        <tr>
                                            <th>Pegawai</th>
                                            <th>Departemen</th>
                                            {columns.map(([k, v]) => (
                                                <th key={k}>{v}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.employees.data.map((e) => (
                                            <tr key={e.id}>
                                                <td>
                                                    <Link
                                                        href={`/attendance/employees/${e.id}?year=${report.month.slice(0, 4)}&date=${report.month}-01`}
                                                    >
                                                        {e.full_name}
                                                    </Link>
                                                    <small className="ep-cell-sub">
                                                        {e.employee_number} ·{' '}
                                                        {e.archived ? 'Diarsipkan' : e.status_label}
                                                    </small>
                                                </td>
                                                <td>{e.department}</td>
                                                {columns.map(([k]) => (
                                                    <td key={k}>{e.counts[k]}</td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                    {report.total > 0 && (
                                        <tfoot>
                                            <tr>
                                                <th colSpan={2}>
                                                    Total seluruh hasil (hari-pegawai)
                                                </th>
                                                {columns.map(([k]) => (
                                                    <td key={k}>{s[k]}</td>
                                                ))}
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </div>
                            {report.total === 0 && (
                                <p className="hr-empty">
                                    Tidak ada pegawai sesuai bulan dan filter yang dipilih.
                                </p>
                            )}
                        </section>
                        <RecordPagination {...p.employees} />
                    </>
                )}
                <section className="ar-notes" aria-label="Cara membaca rekap">
                    <h2>Cara membaca rekap</h2>
                    <p>
                        Tanggal kosong tetap “Belum ada data”, bukan absen. “Tanpa keterangan” hanya
                        dari jadwal yang sudah selesai tanpa masuk atau cuti/izin disetujui. Jadwal
                        malam mengikuti tanggal awal jadwal.
                    </p>
                    <p>
                        “Berjalan” mencakup jadwal belum mulai, sedang berlangsung atau belum
                        pulang. Tanggal mendatang dipisahkan. Cuti/izin/sakit mengikuti tanggal yang
                        disetujui; konflik dengan catatan hadir masuk “Perlu diperiksa”. Pengajuan
                        koreksi yang masih menunggu belum mengubah angka.
                    </p>
                    <p>
                        Pegawai nonaktif/arsip tetap disertakan; label status memakai keadaan saat
                        ini. Hari sebelum bergabung tidak dihitung. Sistem belum merekonstruksi
                        roster dan kewajiban kerja historis dari status pegawai. Rekap ini belum
                        menghitung gaji atau lembur.
                    </p>
                </section>
            </main>
        </HrShell>
    );
}
