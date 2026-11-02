import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowLeft, ArrowRight, Plus, Search } from 'lucide-react';
import HrShell from '../components/hr-shell';
import RecruitmentTabs from '../components/recruitment-tabs';
import { StatusBadge, type StatusVariant } from '../components/ui/status-badge';
import type { VacancyProps, VacancyRecord } from '../types/vacancies';
import '../css/employees.css';
import '../css/vacancies.css';

const date = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' }).format(
        new Date(`${value}T00:00:00Z`),
    );
const tone = (item: VacancyRecord): StatusVariant =>
    item.state_label === 'Dibuka'
        ? 'success'
        : item.state_label === 'Terjadwal'
          ? 'pending'
          : item.state_label === 'Draft'
            ? 'review'
            : 'expired';
const labels = {
    job_title: 'Judul lowongan',
    location: 'Lokasi kerja',
    description: 'Deskripsi pekerjaan',
    requirements: 'Persyaratan',
    opening_date: 'Tanggal buka',
    closing_date: 'Tanggal tutup',
};

function VacancyForm({ vacancy, departments, types, statuses, today }: VacancyProps) {
    const form = useForm({
        job_title: vacancy?.job_title ?? '',
        department_id: String(vacancy?.department_id ?? ''),
        employment_type: vacancy?.employment_type ?? 'full_time',
        location: vacancy?.location ?? '',
        description: vacancy?.description ?? '',
        requirements: vacancy?.requirements ?? '',
        opening_date: vacancy?.opening_date ?? today,
        closing_date: vacancy?.closing_date ?? '',
        status: vacancy?.status ?? 'draft',
        version: vacancy?.version ?? 1,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (vacancy) form.put(`/vacancies/${vacancy.id}`);
        else form.post('/vacancies');
    };
    return (
        <form className="ep-card ep-form" onSubmit={submit}>
            <div className="ep-section-heading">
                <h2>Informasi lowongan</h2>
                <p>Siapkan kebutuhan posisi dan periode rekrutmen.</p>
            </div>
            <div className="ep-alert">
                Status Dibuka menampilkan lowongan di halaman karier publik selama periode
                penerimaan. Gunakan Draft untuk menyiapkan isi sebelum ditayangkan.
            </div>
            {form.errors.version && (
                <p className="ep-error" role="alert">
                    {form.errors.version}{' '}
                    <Link href={`/vacancies/${vacancy?.id}/edit`}>Muat ulang formulir</Link>
                </p>
            )}
            <div className="ep-form-grid">
                {(['job_title', 'location'] as const).map((field) => (
                    <div className="ep-field" key={field}>
                        <label htmlFor={field}>{labels[field]}</label>
                        <input
                            id={field}
                            required
                            maxLength={255}
                            value={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.value)}
                            aria-invalid={!!form.errors[field]}
                        />
                        {form.errors[field] && (
                            <p className="ep-error" role="alert">
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
                        onChange={(e) => form.setData('department_id', e.target.value)}
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
                    <label htmlFor="employment_type">Jenis kerja</label>
                    <select
                        id="employment_type"
                        value={form.data.employment_type}
                        onChange={(e) => form.setData('employment_type', e.target.value)}
                    >
                        {types.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    {form.errors.employment_type && (
                        <p className="ep-error" role="alert">
                            {form.errors.employment_type}
                        </p>
                    )}
                </div>
                {(['description', 'requirements'] as const).map((field) => (
                    <div className="ep-field vc-wide" key={field}>
                        <label htmlFor={field}>{labels[field]}</label>
                        <textarea
                            id={field}
                            required
                            minLength={20}
                            maxLength={10000}
                            rows={5}
                            value={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.value)}
                            aria-invalid={!!form.errors[field]}
                        />
                        <small>Minimal 20 karakter. Pisahkan tiap poin dengan baris baru.</small>
                        {form.errors[field] && (
                            <p className="ep-error" role="alert">
                                {form.errors[field]}
                            </p>
                        )}
                    </div>
                ))}
                {(['opening_date', 'closing_date'] as const).map((field) => (
                    <div className="ep-field" key={field}>
                        <label htmlFor={field}>{labels[field]}</label>
                        <input
                            id={field}
                            type="date"
                            required
                            min={field === 'closing_date' ? form.data.opening_date : undefined}
                            value={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.value)}
                            aria-invalid={!!form.errors[field]}
                        />
                        {form.errors[field] && (
                            <p className="ep-error" role="alert">
                                {form.errors[field]}
                            </p>
                        )}
                    </div>
                ))}
                <div className="ep-field vc-wide">
                    <label htmlFor="status">Status lowongan</label>
                    <select
                        id="status"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        {statuses.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    <small>
                        Draft untuk persiapan. Dibuka aktif dalam periode yang dipilih. Ditutup
                        menghentikan lowongan tanpa menghapus catatannya.
                    </small>
                    {form.errors.status && (
                        <p className="ep-error" role="alert">
                            {form.errors.status}
                        </p>
                    )}
                </div>
            </div>
            <div className="ep-form-footer">
                <Link
                    className="hr-button"
                    href={vacancy ? `/vacancies/${vacancy.id}` : '/vacancies'}
                >
                    Batal
                </Link>
                <button className="ep-primary" disabled={form.processing || !departments.length}>
                    {form.processing ? 'Menyimpan…' : 'Simpan lowongan'}
                    <ArrowRight size={15} />
                </button>
            </div>
        </form>
    );
}

function Directory({ vacancies, filters = {}, departments, statuses, canManage }: VacancyProps) {
    const [q, setQ] = useState(filters.q ?? '');
    const update = (data: Record<string, string>) =>
        router.get(
            '/vacancies',
            { ...filters, q, ...data, page: 1 },
            { preserveState: false, preserveScroll: true },
        );
    return (
        <>
            <div className="ep-filters">
                <form
                    className="ep-search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        update({ q });
                    }}
                >
                    <Search size={16} />
                    <input
                        aria-label="Cari lowongan"
                        placeholder="Cari judul atau lokasi…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
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
                    aria-label="Filter status lowongan"
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
                    aria-label="Urutan lowongan"
                    value={filters.sort ?? 'latest'}
                    onChange={(e) => update({ sort: e.target.value })}
                >
                    <option value="latest">Terbaru</option>
                    <option value="title">Judul A–Z</option>
                    <option value="closing">Tanggal tutup terdekat</option>
                </select>
            </div>
            <section className="hr-panel">
                <div className="hr-table-scroll">
                    <table>
                        <caption className="sr-only">Daftar lowongan internal</caption>
                        <thead>
                            <tr>
                                <th>Lowongan</th>
                                <th>Departemen</th>
                                <th>Jenis kerja</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {vacancies?.data.map((item) => (
                                <tr key={item.id}>
                                    <td>
                                        <Link href={`/vacancies/${item.id}`}>
                                            <strong>{item.job_title}</strong>
                                            <small>{item.location}</small>
                                        </Link>
                                    </td>
                                    <td>{item.department}</td>
                                    <td>{item.type_label}</td>
                                    <td>
                                        <span>{date(item.opening_date)}</span>
                                        <small>s.d. {date(item.closing_date)}</small>
                                    </td>
                                    <td>
                                        <StatusBadge variant={tone(item)}>
                                            {item.state_label}
                                        </StatusBadge>
                                    </td>
                                    <td>
                                        <Link
                                            className="hr-text-button"
                                            href={`/vacancies/${item.id}`}
                                        >
                                            Detail <ArrowRight size={13} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!vacancies?.data.length && (
                    <div className="ep-empty">
                        <h2>Belum ada lowongan yang ditampilkan</h2>
                        <p>Buat lowongan pertama atau sesuaikan pencarian dan filter.</p>
                        {canManage && (
                            <Link className="ep-primary" href="/vacancies/create">
                                <Plus size={15} />
                                Tambah lowongan
                            </Link>
                        )}
                        <Link href="/vacancies">Atur ulang filter</Link>
                    </div>
                )}
                <div className="ep-pagination">
                    <span>
                        {vacancies?.from ?? 0}–{vacancies?.to ?? 0} dari {vacancies?.total ?? 0}{' '}
                        lowongan
                    </span>
                    <nav aria-label="Halaman lowongan">
                        {vacancies?.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    aria-current={link.active ? 'page' : undefined}
                                >
                                    {index === 0
                                        ? 'Sebelumnya'
                                        : index === vacancies.links.length - 1
                                          ? 'Berikutnya'
                                          : link.label}
                                </Link>
                            ) : (
                                <span key={index}>
                                    {index === 0
                                        ? 'Sebelumnya'
                                        : index === vacancies.links.length - 1
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

export default function Vacancies(props: VacancyProps) {
    const { auth, mode, vacancy, canManage, flash } = props;
    const title =
        mode === 'create'
            ? 'Tambah lowongan'
            : mode === 'edit'
              ? 'Edit lowongan'
              : mode === 'show'
                ? 'Detail lowongan'
                : 'Lowongan';
    return (
        <HrShell title={title} active="recruitment" user={auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                <RecruitmentTabs active="vacancies" />
                {mode !== 'index' && (
                    <Link className="ep-back" href="/vacancies">
                        <ArrowLeft size={14} />
                        Kembali ke lowongan
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {mode === 'index'
                                ? 'Kelola kebutuhan posisi dan periode rekrutmen tim.'
                                : vacancy
                                  ? `${vacancy.job_title} · ${vacancy.department}`
                                  : 'Mulai dari draft, lengkapi kebutuhan posisi, lalu buka rekrutmen.'}
                        </p>
                    </div>
                    {mode === 'index' && canManage && (
                        <Link className="ep-primary" href="/vacancies/create">
                            <Plus size={16} />
                            Tambah lowongan
                        </Link>
                    )}
                </div>
                {flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {flash.success}
                    </p>
                )}
                {mode === 'index' && <Directory key={JSON.stringify(props.filters)} {...props} />}
                {(mode === 'create' || mode === 'edit') && (
                    <VacancyForm key={`${mode}-${vacancy?.id}`} {...props} />
                )}
                {mode === 'show' && vacancy && (
                    <section className="ep-card vc-detail">
                        <div className="vc-detail-head">
                            <div>
                                <h2>{vacancy.job_title}</h2>
                                <p>{vacancy.location}</p>
                            </div>
                            <StatusBadge variant={tone(vacancy)}>{vacancy.state_label}</StatusBadge>
                        </div>
                        <dl className="ep-details">
                            <div>
                                <dt>Departemen</dt>
                                <dd>{vacancy.department}</dd>
                            </div>
                            <div>
                                <dt>Jenis kerja</dt>
                                <dd>{vacancy.type_label}</dd>
                            </div>
                            <div>
                                <dt>Tanggal buka</dt>
                                <dd>{date(vacancy.opening_date)}</dd>
                            </div>
                            <div>
                                <dt>Tanggal tutup</dt>
                                <dd>{date(vacancy.closing_date)}</dd>
                            </div>
                        </dl>
                        <div className="vc-copy">
                            <h3>Deskripsi pekerjaan</h3>
                            <p>{vacancy.description}</p>
                            <h3>Persyaratan</h3>
                            <p>{vacancy.requirements}</p>
                        </div>
                        <p className="ep-alert">
                            Lowongan berstatus Dibuka tampil di halaman karier publik selama periode
                            penerimaan. Untuk menghentikan penayangan, ubah status menjadi Ditutup;
                            catatan tetap tersimpan.
                        </p>
                        {vacancy.state_label === 'Dibuka' && (
                            <p>
                                <Link className="hr-text-button" href={`/karier/${vacancy.id}`}>
                                    Lihat halaman publik →
                                </Link>
                            </p>
                        )}
                        {canManage && (
                            <div className="ep-detail-actions">
                                <Link className="ep-primary" href={`/vacancies/${vacancy.id}/edit`}>
                                    Edit lowongan / status
                                </Link>
                            </div>
                        )}
                    </section>
                )}
                <p className="ep-bottom-note">
                    Data lowongan tersimpan di database. Kelola lamaran melalui tab Pipeline
                    kandidat.
                </p>
            </main>
        </HrShell>
    );
}
