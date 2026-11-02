import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowLeft, ArrowRight, FileText, Plus, Search } from 'lucide-react';
import HrShell from '../components/hr-shell';
import { StatusBadge } from '../components/ui/status-badge';
import { initials } from '../data/hr-preview';
import type { EmployeeProps, EmployeeRecord } from '../types/employees';
import '../css/employees.css';

function date(value: string, time = false) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        ...(time ? { timeStyle: 'short', timeZone: 'Asia/Jakarta' } : { timeZone: 'UTC' }),
    }).format(new Date(time ? value : `${value}T00:00:00Z`));
}
const labels: Record<string, string> = {
    employee_number: 'Nomor pegawai',
    full_name: 'Nama lengkap',
    work_email: 'Email kerja',
    phone: 'Telepon',
    department_id: 'Departemen',
    position_id: 'Jabatan',
    employment_status: 'Status kerja',
    join_date: 'Tanggal bergabung',
};
const actions: Record<string, string> = {
    created: 'Pegawai ditambahkan',
    updated: 'Data diperbarui',
    archived: 'Pegawai diarsipkan',
    restored: 'Pegawai dipulihkan',
};

function EmployeeForm({
    employee,
    departments,
    positions,
    statuses,
}: Pick<EmployeeProps, 'employee' | 'departments' | 'positions' | 'statuses'>) {
    const form = useForm({
        employee_number: employee?.employee_number ?? '',
        full_name: employee?.full_name ?? '',
        work_email: employee?.work_email ?? '',
        phone: employee?.phone ?? '',
        department_id: String(employee?.department_id ?? ''),
        position_id: String(employee?.position_id ?? ''),
        employment_status: employee?.employment_status ?? 'permanent',
        join_date: employee?.join_date ?? '',
        version: employee?.version ?? 1,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (employee) form.put(`/employees/${employee.id}`);
        else form.post('/employees');
    };
    const fields = ['employee_number', 'full_name', 'work_email', 'phone', 'join_date'] as const;
    return (
        <form className="ep-form ep-card" onSubmit={submit}>
            <div className="ep-section-heading">
                <h2>Informasi pegawai</h2>
                <p>Isi identitas kerja, penempatan, dan tanggal bergabung.</p>
            </div>
            {form.errors.version && (
                <div className="ep-alert ep-error" role="alert">
                    {form.errors.version}{' '}
                    <Link href={`/employees/${employee?.id}/edit`}>Muat ulang formulir</Link>
                </div>
            )}
            {!departments.length && (
                <p className="ep-alert">
                    Departemen/jabatan belum tersedia. Hubungi pengelola untuk mengisi master
                    penempatan.
                </p>
            )}
            <div className="ep-form-grid">
                {fields.map((field) => (
                    <div className="ep-field" key={field}>
                        <label htmlFor={field}>
                            {labels[field]}
                            {field === 'phone' ? ' (opsional)' : ''}
                        </label>
                        <input
                            id={field}
                            name={field}
                            type={
                                field === 'join_date'
                                    ? 'date'
                                    : field === 'work_email'
                                      ? 'email'
                                      : field === 'phone'
                                        ? 'tel'
                                        : 'text'
                            }
                            required={field !== 'phone'}
                            maxLength={
                                field === 'employee_number' ? 50 : field === 'phone' ? 32 : 255
                            }
                            value={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.value)}
                            aria-invalid={!!form.errors[field]}
                            aria-describedby={form.errors[field] ? `${field}-error` : undefined}
                        />
                        {form.errors[field] && (
                            <p id={`${field}-error`} className="ep-error" role="alert">
                                {form.errors[field]}
                            </p>
                        )}
                    </div>
                ))}
                <div className="ep-field">
                    <label htmlFor="department_id">Departemen</label>
                    <select
                        id="department_id"
                        required
                        value={form.data.department_id}
                        onChange={(e) =>
                            form.setData({
                                ...form.data,
                                department_id: e.target.value,
                                position_id: '',
                            })
                        }
                        aria-invalid={!!form.errors.department_id}
                    >
                        <option value="">Pilih departemen</option>
                        {departments.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    {form.errors.department_id && (
                        <p className="ep-error" role="alert">
                            {form.errors.department_id}
                        </p>
                    )}
                </div>
                <div className="ep-field">
                    <label htmlFor="position_id">Jabatan</label>
                    <select
                        id="position_id"
                        required
                        disabled={!form.data.department_id}
                        value={form.data.position_id}
                        onChange={(e) => form.setData('position_id', e.target.value)}
                        aria-invalid={!!form.errors.position_id}
                    >
                        <option value="">Pilih jabatan</option>
                        {positions
                            .filter(
                                (item) => String(item.department_id) === form.data.department_id,
                            )
                            .map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                    </select>
                    {form.errors.position_id && (
                        <p className="ep-error" role="alert">
                            {form.errors.position_id}
                        </p>
                    )}
                </div>
                <div className="ep-field">
                    <label htmlFor="employment_status">Status kerja</label>
                    <select
                        id="employment_status"
                        value={form.data.employment_status}
                        onChange={(e) => form.setData('employment_status', e.target.value)}
                    >
                        {statuses.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    {form.errors.employment_status && (
                        <p className="ep-error" role="alert">
                            {form.errors.employment_status}
                        </p>
                    )}
                </div>
            </div>
            <div className="ep-form-footer">
                <Link
                    className="hr-button"
                    href={employee ? `/employees/${employee.id}` : '/employees'}
                >
                    Batal
                </Link>
                <button className="ep-primary" disabled={form.processing || !departments.length}>
                    {form.processing
                        ? 'Menyimpan…'
                        : employee
                          ? 'Simpan perubahan'
                          : 'Tambah pegawai'}
                    <ArrowRight size={15} />
                </button>
            </div>
        </form>
    );
}

function ArchiveForm({
    employee,
    restore = false,
}: {
    employee: EmployeeRecord;
    restore?: boolean;
}) {
    const form = useForm<{
        reason: string;
        confirmation: string;
        acknowledged: boolean;
        version: number;
        document: File | null;
    }>({
        reason: '',
        confirmation: '',
        acknowledged: false,
        version: employee.version,
        document: null,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/employees/${employee.id}/${restore ? 'restore' : 'archive'}`, {
            forceFormData: !restore,
            onSuccess: () => form.reset(),
        });
    };
    return (
        <form className="ep-card ep-form ep-archive-form" onSubmit={submit}>
            <div className="ep-section-heading">
                <h2>{restore ? 'Pulihkan ke direktori aktif' : 'Arsipkan pegawai'}</h2>
                <p>
                    {restore
                        ? 'Riwayat arsip dan berkas pendukung akan tetap tersimpan.'
                        : 'Pegawai dikeluarkan dari daftar aktif. Data dan riwayatnya tetap tersimpan.'}
                </p>
            </div>
            {!restore && (
                <div className="ep-alert">
                    <strong>Pastikan jenis tindak lanjut sudah tepat.</strong>
                    <p>
                        Jika pegawai berhenti bekerja, ubah status menjadi Nonaktif agar riwayat
                        hubungan kerja tetap terlihat. Gunakan arsip untuk catatan yang memang perlu
                        dikeluarkan dari direktori, misalnya data duplikat atau salah input.
                    </p>
                </div>
            )}
            {form.errors.version && (
                <p className="ep-error" role="alert">
                    {form.errors.version}{' '}
                    <Link href={`/employees/${employee.id}${restore ? '' : '/archive'}`}>
                        Muat ulang
                    </Link>
                </p>
            )}
            <div className="ep-field">
                <label htmlFor="reason">Alasan {restore ? 'pemulihan' : 'pengarsipan'}</label>
                <textarea
                    id="reason"
                    required
                    minLength={20}
                    maxLength={1000}
                    rows={4}
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    aria-invalid={!!form.errors.reason}
                />
                <small>Minimal 20 karakter. Jelaskan alasan agar bisa diperiksa kembali.</small>
                {form.errors.reason && (
                    <p className="ep-error" role="alert">
                        {form.errors.reason}
                    </p>
                )}
            </div>
            {!restore && (
                <div className="ep-field">
                    <label htmlFor="document">Berkas pendukung</label>
                    <input
                        id="document"
                        type="file"
                        required
                        accept="application/pdf,.pdf"
                        onChange={(e) => form.setData('document', e.target.files?.[0] ?? null)}
                        aria-invalid={!!form.errors.document}
                    />
                    <small>
                        PDF maksimal 5 MiB. Contoh: persetujuan koreksi atau berita acara
                        pemeriksaan data. Berkas hanya dapat diakses Admin.
                    </small>
                    {form.errors.document && (
                        <p className="ep-error" role="alert">
                            {form.errors.document}
                        </p>
                    )}
                </div>
            )}
            <div className="ep-field">
                <label htmlFor="confirmation">
                    Ketik <strong>{employee.employee_number}</strong> untuk mengonfirmasi
                </label>
                <input
                    id="confirmation"
                    autoComplete="off"
                    required
                    value={form.data.confirmation}
                    onChange={(e) => form.setData('confirmation', e.target.value)}
                    aria-invalid={!!form.errors.confirmation}
                />
                {form.errors.confirmation && (
                    <p className="ep-error" role="alert">
                        {form.errors.confirmation}
                    </p>
                )}
            </div>
            {!restore && (
                <div>
                    <label className="ep-checkbox">
                        <input
                            type="checkbox"
                            checked={form.data.acknowledged}
                            required
                            onChange={(e) => form.setData('acknowledged', e.target.checked)}
                        />{' '}
                        Saya sudah memeriksa berkas dan memastikan pegawai yang dipilih benar.
                    </label>
                    {form.errors.acknowledged && (
                        <p className="ep-error" role="alert">
                            {form.errors.acknowledged}
                        </p>
                    )}
                </div>
            )}
            {form.progress && <p role="status">Mengunggah berkas: {form.progress.percentage}%</p>}
            <div className="ep-form-footer">
                <Link className="hr-button" href={`/employees/${employee.id}`}>
                    Kembali ke detail
                </Link>
                <button
                    className={restore ? 'ep-primary' : 'ep-danger'}
                    disabled={
                        form.processing ||
                        form.data.confirmation !== employee.employee_number ||
                        form.data.reason.trim().length < 20 ||
                        (!restore && (!form.data.document || !form.data.acknowledged))
                    }
                >
                    {form.processing
                        ? 'Memproses…'
                        : restore
                          ? 'Pulihkan pegawai'
                          : 'Arsipkan pegawai'}
                </button>
            </div>
        </form>
    );
}

function EmployeeDirectory(props: EmployeeProps) {
    const { employees, filters = {}, departments, statuses, can } = props;
    const [search, setSearch] = useState(filters.q ?? '');
    const update = (values: Record<string, string | boolean>) =>
        router.get(
            '/employees',
            { ...filters, ...values, archived: filters.archived ? 1 : 0, page: 1 },
            { preserveState: false, preserveScroll: true },
        );
    return (
        <>
            <div className="ep-tabs">
                <Link href="/employees" aria-current={!filters.archived ? 'page' : undefined}>
                    Direktori aktif
                </Link>
                {can.admin && (
                    <Link
                        href="/employees?archived=1"
                        aria-current={filters.archived ? 'page' : undefined}
                    >
                        Arsip
                    </Link>
                )}
                <span>{employees?.total ?? 0} pegawai</span>
            </div>
            <div className="ep-filters">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        update({ q: search });
                    }}
                    className="ep-search"
                >
                    <Search size={16} />
                    <input
                        aria-label="Cari pegawai"
                        placeholder="Cari nama, nomor, atau email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <button type="submit">Cari</button>
                </form>
                <select
                    aria-label="Filter departemen"
                    value={filters.department_id ?? ''}
                    onChange={(e) => update({ department_id: e.target.value })}
                >
                    <option value="">Semua departemen</option>
                    {departments.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.name}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Filter status kerja"
                    value={filters.status ?? ''}
                    onChange={(e) => update({ status: e.target.value })}
                >
                    <option value="">Semua status</option>
                    {statuses.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Urutan pegawai"
                    value={filters.sort ?? 'name_asc'}
                    onChange={(e) => update({ sort: e.target.value })}
                >
                    <option value="name_asc">Nama A–Z</option>
                    <option value="name_desc">Nama Z–A</option>
                    <option value="joined_desc">Bergabung terbaru</option>
                </select>
            </div>
            <section className="hr-panel">
                <div className="hr-table-scroll">
                    <table>
                        <caption className="sr-only">
                            Direktori pegawai {filters.archived ? 'arsip' : 'aktif'}
                        </caption>
                        <thead>
                            <tr>
                                <th>Pegawai</th>
                                <th>Departemen</th>
                                <th>Jabatan</th>
                                <th>Bergabung</th>
                                <th>Status</th>
                                <th>
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees?.data.map((employee) => (
                                <tr key={employee.id}>
                                    <td>
                                        <Link
                                            className="hr-person"
                                            href={`/employees/${employee.id}`}
                                        >
                                            <span className="hr-avatar">
                                                {initials(employee.full_name)}
                                            </span>
                                            <span>
                                                <strong>{employee.full_name}</strong>
                                                <small>{employee.employee_number}</small>
                                            </span>
                                        </Link>
                                    </td>
                                    <td>{employee.department}</td>
                                    <td>{employee.position}</td>
                                    <td>{date(employee.join_date)}</td>
                                    <td>
                                        <StatusBadge
                                            variant={
                                                employee.archived_at
                                                    ? 'expired'
                                                    : employee.employment_status === 'permanent'
                                                      ? 'success'
                                                      : employee.employment_status === 'inactive'
                                                        ? 'expired'
                                                        : 'progress'
                                            }
                                        >
                                            {employee.archived_at
                                                ? 'Diarsipkan'
                                                : employee.status_label}
                                        </StatusBadge>
                                    </td>
                                    <td>
                                        <Link
                                            className="hr-text-button"
                                            href={`/employees/${employee.id}`}
                                        >
                                            Detail <ArrowRight size={13} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!employees?.data.length && (
                    <div className="ep-empty">
                        <h2>
                            {employees?.total
                                ? 'Halaman ini tidak memiliki pegawai'
                                : 'Belum ada pegawai yang ditampilkan'}
                        </h2>
                        <p>
                            {filters.archived
                                ? 'Pegawai yang diarsipkan akan tersedia di sini.'
                                : 'Tambahkan pegawai pertama atau sesuaikan pencarian dan filter Anda.'}
                        </p>
                        {can.create && !filters.archived && (
                            <Link className="ep-primary" href="/employees/create">
                                <Plus size={15} />
                                Tambah pegawai
                            </Link>
                        )}
                        <Link href={filters.archived ? '/employees?archived=1' : '/employees'}>
                            Atur ulang filter
                        </Link>
                    </div>
                )}
                <div className="ep-pagination">
                    <span>
                        {employees?.from ?? 0}–{employees?.to ?? 0} dari {employees?.total ?? 0}{' '}
                        pegawai
                    </span>
                    <nav aria-label="Halaman pegawai">
                        {employees?.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    aria-current={link.active ? 'page' : undefined}
                                >
                                    {index === 0
                                        ? 'Sebelumnya'
                                        : index === employees.links.length - 1
                                          ? 'Berikutnya'
                                          : link.label}
                                </Link>
                            ) : (
                                <span key={index}>
                                    {index === 0
                                        ? 'Sebelumnya'
                                        : index === employees.links.length - 1
                                          ? 'Berikutnya'
                                          : link.label}
                                </span>
                            ),
                        )}
                    </nav>
                </div>
            </section>
        </>
    );
}

export default function Employees(props: EmployeeProps) {
    const { mode, employee, auth, can, flash, events } = props;
    const title =
        mode === 'create'
            ? 'Tambah pegawai'
            : mode === 'edit'
              ? 'Edit pegawai'
              : mode === 'archive'
                ? 'Arsipkan pegawai'
                : mode === 'show'
                  ? 'Detail pegawai'
                  : 'Pegawai';
    return (
        <HrShell title={title} active="employees" user={auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                {mode !== 'index' && (
                    <Link className="ep-back" href="/employees">
                        <ArrowLeft size={14} />
                        Kembali ke direktori
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {mode === 'index'
                                ? 'Direktori pegawai dan penempatan tim Anda.'
                                : employee
                                  ? `${employee.full_name} · ${employee.employee_number}`
                                  : 'Mulai catatan pegawai dengan identitas kerja yang lengkap.'}
                        </p>
                    </div>
                    {mode === 'index' && can.create && (
                        <Link className="ep-primary" href="/employees/create">
                            <Plus size={16} />
                            Tambah pegawai
                        </Link>
                    )}
                </div>
                {flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {flash.success}
                    </p>
                )}
                {mode === 'index' && (
                    <EmployeeDirectory key={JSON.stringify(props.filters)} {...props} />
                )}
                {(mode === 'create' || mode === 'edit') && (
                    <EmployeeForm key={`${mode}-${employee?.id}`} {...props} />
                )}
                {mode === 'archive' && employee && (
                    <ArchiveForm key={employee.id} employee={employee} />
                )}
                {mode === 'show' && employee && (
                    <>
                        <section className="ep-card ep-profile-card">
                            <div className="ep-profile-head">
                                <span className="hr-avatar hr-avatar-large">
                                    {initials(employee.full_name)}
                                </span>
                                <div>
                                    <h2>{employee.full_name}</h2>
                                    <p>
                                        {employee.employee_number} · {employee.department}
                                    </p>
                                </div>
                                <StatusBadge variant={employee.archived_at ? 'expired' : 'success'}>
                                    {employee.archived_at ? 'Diarsipkan' : employee.status_label}
                                </StatusBadge>
                            </div>
                            <dl className="ep-details">
                                {Object.entries({
                                    employee_number: employee.employee_number,
                                    work_email: employee.work_email,
                                    phone: employee.phone ?? '—',
                                    department_id: employee.department,
                                    position_id: employee.position,
                                    employment_status: employee.status_label,
                                    join_date: date(employee.join_date),
                                }).map(([key, value]) => (
                                    <div key={key}>
                                        <dt>{labels[key]}</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="ep-detail-actions">
                                {can.create && !employee.archived_at && (
                                    <Link
                                        className="ep-primary"
                                        href={`/employees/${employee.id}/edit`}
                                    >
                                        Edit pegawai
                                    </Link>
                                )}
                                {can.admin && !employee.archived_at && (
                                    <Link
                                        className="ep-archive-link"
                                        href={`/employees/${employee.id}/archive`}
                                    >
                                        Arsipkan pegawai
                                    </Link>
                                )}
                            </div>
                        </section>
                        {can.admin && (
                            <section className="ep-card ep-history">
                                <div className="ep-section-heading">
                                    <h2>Riwayat pencatatan</h2>
                                    <p>Aktivitas, pelaku, alasan, dan berkas pendukung.</p>
                                </div>
                                {events.length ? (
                                    events.map((event) => (
                                        <article key={event.id}>
                                            <div>
                                                <strong>
                                                    {actions[event.action] ?? event.action}
                                                </strong>
                                                <time dateTime={event.created_at}>
                                                    {date(event.created_at, true)} WIB
                                                </time>
                                            </div>
                                            <p>{event.actor}</p>
                                            {event.reason && (
                                                <p className="ep-reason">{event.reason}</p>
                                            )}
                                            {event.changes && (
                                                <details>
                                                    <summary>Rincian perubahan</summary>
                                                    <dl className="ep-changes">
                                                        {Object.entries(event.changes.after)
                                                            .filter(
                                                                ([key, value]) =>
                                                                    event.changes?.before[key] !==
                                                                    value,
                                                            )
                                                            .map(([key, value]) => (
                                                                <div key={key}>
                                                                    <dt>{labels[key] ?? key}</dt>
                                                                    <dd>
                                                                        {event.action ===
                                                                            'updated' && (
                                                                            <span>
                                                                                {String(
                                                                                    event.changes
                                                                                        ?.before[
                                                                                        key
                                                                                    ] ?? '—',
                                                                                )}{' '}
                                                                                →{' '}
                                                                            </span>
                                                                        )}
                                                                        {String(value ?? '—')}
                                                                    </dd>
                                                                </div>
                                                            ))}
                                                    </dl>
                                                </details>
                                            )}
                                            {event.has_document && (
                                                <a
                                                    className="ep-download"
                                                    href={`/employees/${employee.id}/events/${event.id}/document`}
                                                >
                                                    <FileText size={15} />
                                                    Unduh berkas pendukung
                                                </a>
                                            )}
                                        </article>
                                    ))
                                ) : (
                                    <p>Belum ada riwayat pencatatan.</p>
                                )}
                            </section>
                        )}
                        {employee.archived_at && can.admin && (
                            <ArchiveForm employee={employee} restore />
                        )}
                    </>
                )}
                <p className="ep-bottom-note">
                    Perubahan pegawai tersimpan di database. Akses dan tindakan mengikuti peran
                    akun.
                </p>
            </main>
        </HrShell>
    );
}
