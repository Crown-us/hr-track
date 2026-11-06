import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Download, FileUp, Upload } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field } from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import { StatusBadge } from '../components/ui/status-badge';
import '../css/employees.css';
import '../css/applications.css';
import '../css/trainings.css';
import '../css/reports.css';
import '../css/imports.css';

type PreviewRow = {
    line: number;
    employee_number: string;
    full_name: string;
    work_email: string;
    department: string;
    position: string;
    status: string;
    join_date: string;
    errors: string[];
};
type Props = {
    auth: { user: HrUser };
    errors?: Record<string, string>;
    limits: { rows: number; megabytes: number };
    columns: { label: string; required: boolean }[];
    preview: {
        token: string;
        file: string;
        total: number;
        valid: number;
        invalid: number;
        rows: PreviewRow[];
    } | null;
};

const isoDate = /^\d{4}-\d{2}-\d{2}$/;

function UploadCard({ p }: { p: Props }) {
    const form = useForm<{ file: File | null }>({ file: null });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/employees/import/preview', { forceFormData: true });
    };
    return (
        <form className="ep-card ip-upload" onSubmit={submit} encType="multipart/form-data">
            <div className="ep-section-heading">
                <h2>1. Unggah file</h2>
                <p>
                    Berkas .csv atau .xlsx, maksimal {p.limits.megabytes} MB dan {p.limits.rows}{' '}
                    baris. Tidak ada data yang tersimpan sebelum Anda mengonfirmasi pratinjau.
                </p>
            </div>
            <Field label="File pegawai" error={p.preview ? undefined : p.errors?.file}>
                <input
                    type="file"
                    accept=".csv,.xlsx"
                    required
                    onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                />
            </Field>
            <div className="ip-actions">
                <button className="ep-primary" disabled={form.processing || !form.data.file}>
                    <Upload size={15} />
                    {form.processing ? 'Memeriksa…' : 'Periksa file'}
                </button>
                <a className="ep-secondary" href="/employees/import/template">
                    <Download size={15} />
                    Unduh template CSV
                </a>
            </div>
            <div>
                <strong>Kolom yang dibaca</strong>
                <ul className="ip-columns">
                    {p.columns.map((c) => (
                        <li key={c.label}>
                            {c.label}
                            <small>{c.required ? 'wajib' : 'opsional'}</small>
                        </li>
                    ))}
                </ul>
                <ul className="ip-rules">
                    <li>Judul kolom ada di baris pertama; urutan kolom bebas.</li>
                    <li>
                        Departemen dan jabatan harus sudah ada. Isi dengan nama departemen atau
                        kodenya.
                    </li>
                    <li>Status kerja: Tetap, Kontrak, Percobaan, Magang, atau Nonaktif.</li>
                    <li>Tanggal: 2026-01-31 atau 31/01/2026. Kolom tanggal Excel juga dikenali.</li>
                    <li>Format kolom telepon sebagai teks agar angka 0 di depan tidak hilang.</li>
                </ul>
            </div>
        </form>
    );
}

function PreviewCard({
    preview,
    error,
}: {
    preview: NonNullable<Props['preview']>;
    error?: string;
}) {
    const form = useForm({ batch: preview.token });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/employees/import');
    };
    const blocked = preview.invalid > 0;
    return (
        <section className="hr-panel" aria-labelledby="ip-preview-title">
            <div className="rp-heading">
                <div>
                    <h2 id="ip-preview-title">2. Periksa pratinjau</h2>
                    <p>File: {preview.file || 'tanpa nama'}</p>
                </div>
            </div>
            {error && (
                <p role="alert" className="ep-alert ip-alert">
                    {error}
                </p>
            )}
            <div className="ip-summary">
                <span>
                    <strong>{preview.total}</strong> baris dibaca
                </span>
                <span>
                    <strong>{preview.valid}</strong> siap diimpor
                </span>
                <span>
                    <strong>{preview.invalid}</strong> perlu perbaikan
                </span>
            </div>
            <div className="hr-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Nomor</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Departemen</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                            <th>Bergabung</th>
                            <th>Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        {preview.rows.map((r) => (
                            <tr
                                key={r.line}
                                className={r.errors.length ? 'ip-row-error' : undefined}
                            >
                                <td>{r.line}</td>
                                <td>{r.employee_number || '—'}</td>
                                <td>{r.full_name || '—'}</td>
                                <td>{r.work_email || '—'}</td>
                                <td>{r.department || '—'}</td>
                                <td>{r.position || '—'}</td>
                                <td>{r.status || '—'}</td>
                                <td>
                                    {isoDate.test(r.join_date)
                                        ? date(r.join_date)
                                        : r.join_date || '—'}
                                </td>
                                <td>
                                    {r.errors.length ? (
                                        <>
                                            <StatusBadge variant="failed">
                                                Perlu perbaikan
                                            </StatusBadge>
                                            <ul className="ip-errors">
                                                {r.errors.map((m) => (
                                                    <li key={m}>{m}</li>
                                                ))}
                                            </ul>
                                        </>
                                    ) : (
                                        <StatusBadge variant="success">Siap</StatusBadge>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <form className="ip-commit" onSubmit={submit}>
                <p>
                    {blocked
                        ? 'Impor berjalan sekaligus: semua baris harus lolos. Perbaiki file sesuai catatan di atas, lalu unggah ulang.'
                        : 'Semua baris lolos pemeriksaan. Pegawai ditambahkan sekaligus dan setiap penambahan tercatat di riwayat pegawai.'}
                </p>
                <button className="ep-primary" disabled={blocked || form.processing}>
                    <FileUp size={15} />
                    {form.processing ? 'Mengimpor…' : `Impor ${preview.valid} pegawai`}
                </button>
            </form>
        </section>
    );
}

export default function EmployeeImport(p: Props) {
    return (
        <HrShell title="Impor pegawai" active="employees" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main ip-page">
                <div className="hr-page-heading">
                    <div>
                        <h1>Impor pegawai</h1>
                        <p>Tambahkan banyak pegawai sekaligus dari file Excel atau CSV.</p>
                    </div>
                </div>
                <p>
                    <Link className="ep-back" href="/employees">
                        Kembali ke direktori pegawai
                    </Link>
                </p>
                <UploadCard key={p.preview?.token ?? 'new'} p={p} />
                {p.preview && (
                    <PreviewCard key={p.preview.token} preview={p.preview} error={p.errors?.file} />
                )}
            </main>
        </HrShell>
    );
}
