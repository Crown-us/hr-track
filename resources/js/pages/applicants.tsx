import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';
import { StatusBadge, type StatusVariant } from '../components/ui/status-badge';
import FilterBar from '../components/ui/filter-bar';

interface ApplicationRow {
    id: number;
    vacancy_id: number;
    job_title: string;
    department: string;
    status: string;
    status_label: string;
    application_date: string;
}

interface DocumentRow {
    download_url: string | null;
    id: number;
    size_bytes: number;
    created_at: string;
}

interface ApplicantData {
    id: number;
    full_name: string;
    email?: string;
    phone?: string | null;
    application_count: number;
    applications: ApplicationRow[];
    documents: DocumentRow[];
}

interface ApplicantsProps {
    mode: string;
    applicant: ApplicantData | null;
    applicants: {
        data: ApplicantData[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        flash?: { success?: string };
    };
    filters: { q?: string };
    canCreate: boolean;
    canManage: boolean;
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="ep-field ap-field">
            <span>{label}</span>
            {children}
            {error && (
                <p className="ep-error" role="alert">
                    {error}
                </p>
            )}
        </label>
    );
}

function ApplicantForm({ applicant }: { applicant: ApplicantData | null }) {
    const f = useForm<{ full_name: string; email: string; phone: string }>({
        full_name: applicant?.full_name ?? '',
        email: applicant?.email ?? '',
        phone: applicant?.phone ?? '',
    });

    return (
        <form
            className="ep-card ep-form ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post('/applicants');
            }}
        >
            <div className="ep-section-heading">
                <h2>{applicant ? 'Ubah Profil Pelamar' : 'Tambah Profil Pelamar'}</h2>
            </div>
            <Field label="Nama lengkap" error={f.errors.full_name}>
                <input
                    required
                    maxLength={255}
                    value={f.data.full_name}
                    onChange={(e) => f.setData('full_name', e.target.value)}
                />
            </Field>
            <Field label="Email pelamar" error={f.errors.email}>
                <input
                    required
                    type="email"
                    maxLength={255}
                    value={f.data.email}
                    onChange={(e) => f.setData('email', e.target.value)}
                />
            </Field>
            <Field label="Telepon (opsional)" error={f.errors.phone}>
                <input
                    maxLength={32}
                    value={f.data.phone}
                    onChange={(e) => f.setData('phone', e.target.value)}
                />
            </Field>
            <div className="ep-form-footer">
                <button className="ep-primary" disabled={f.processing}>
                    {f.processing ? 'Menyimpan…' : 'Simpan profil'}
                </button>
                <button
                    type="button"
                    className="hr-button"
                    onClick={() => router.get('/applicants')}
                >
                    Batal
                </button>
            </div>
        </form>
    );
}

const statusTone: Record<string, StatusVariant> = {
    screening: 'review',
    interview: 'progress',
    offering: 'pending',
    accepted: 'success',
    rejected: 'failed',
};

const appDate = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' }).format(
        new Date(`${value}T00:00:00Z`),
    );

export default function Applicants({
    mode,
    applicant,
    applicants,
    filters,
    canCreate,
    canManage,
}: ApplicantsProps) {
    const { auth, flash } = usePage<{ auth: { user: HrUser }; flash: { success?: string } }>()
        .props;
    const rows = applicants?.data ?? [];

    if (mode === 'create') {
        return (
            <HrShell title="Tambah Profil Pelamar" active="applicants" user={auth.user}>
                <main id="hr-main" className="hr-main ep-main">
                    <ApplicantForm applicant={null} />
                </main>
            </HrShell>
        );
    }

    if (mode === 'show' && applicant) {
        return (
            <HrShell title="Detail Profil Pelamar" active="applicants" user={auth.user}>
                <main id="hr-main" className="hr-main ep-main">
                    <div className="hr-page-heading">
                        <div>
                            <h1>{applicant.full_name}</h1>
                            <p>
                                {applicant.email ?? '—'} · {applicant.phone ?? '—'}
                            </p>
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <Link className="hr-button" href="/applicants">
                                <ArrowLeft size={14} /> Kembali ke daftar
                            </Link>
                        </div>
                    </div>
                    {flash?.success && (
                        <p className="ep-alert ep-success" role="status">
                            {flash?.success}
                        </p>
                    )}
                    <div className="ep-cards">
                        <div className="ep-card">
                            <h3>Ringkasan</h3>
                            <p>{applicant.application_count} lamaran tercatat</p>
                        </div>
                    </div>
                    <section className="hr-panel">
                        <h3>Lamaran</h3>
                        <div className="hr-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Lowongan</th>
                                        <th>Departemen</th>
                                        <th>Tanggal</th>
                                        <th>Tahap</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {applicant.applications.map((a) => (
                                        <tr key={a.id}>
                                            <td>
                                                <Link href={`/vacancies/${a.vacancy_id}`}>
                                                    {a.job_title}
                                                </Link>
                                            </td>
                                            <td>{a.department}</td>
                                            <td>{appDate(a.application_date)}</td>
                                            <td>
                                                <StatusBadge
                                                    variant={statusTone[a.status] ?? 'review'}
                                                >
                                                    {a.status_label}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))}
                                    {!applicant.applications.length && (
                                        <tr>
                                            <td colSpan={4}>Belum ada lamaran.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                    {canManage && applicant.documents.length > 0 && (
                        <section className="hr-panel">
                            <h3>CV</h3>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Versi</th>
                                            <th>Ukuran</th>
                                            <th>Diunggah</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {applicant.documents.map((d, i) => (
                                            <tr key={d.id}>
                                                <td>
                                                    CV #{d.id}
                                                    {i === 0 ? ' (terbaru)' : ''}
                                                </td>
                                                <td>{Math.ceil(d.size_bytes / 1024)} KiB</td>
                                                <td>
                                                    {new Intl.DateTimeFormat('id-ID', {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                        timeZone: 'Asia/Jakarta',
                                                    }).format(new Date(d.created_at))}
                                                </td>
                                                <td>
                                                    {d.download_url ? (
                                                        <a
                                                            className="hr-text-button"
                                                            href={d.download_url}
                                                        >
                                                            Unduh
                                                        </a>
                                                    ) : (
                                                        <span>Belum ada lamaran aktif</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}
                </main>
            </HrShell>
        );
    }

    return (
        <HrShell title="Profil Pelamar" active="applicants" user={auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                <div className="hr-page-heading">
                    <div>
                        <h1>Profil Pelamar</h1>
                        <p>Data identitas pelamar dan riwayat lamarannya.</p>
                    </div>
                    {canCreate && (
                        <Link className="ep-primary" href="/applicants/create">
                            <Plus size={16} /> Tambah Profil
                        </Link>
                    )}
                </div>
                {flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {flash?.success}
                    </p>
                )}
                <FilterBar
                    searchPlaceholder={canCreate ? 'Cari nama atau email…' : 'Cari nama…'}
                    q={filters.q ?? ''}
                    departmentId={null}
                    employmentType={null}
                    status={null}
                    sort={null}
                    departments={[]}
                    onChange={(patch: {
                        q?: string;
                        departmentId?: string | null;
                        employmentType?: string | null;
                        status?: string | null;
                        sort?: string | null;
                    }) => {
                        const next: Record<string, string | null> = { ...filters, page: '1' };
                        if (patch.q !== undefined) next.q = patch.q ?? null;
                        router.get('/applicants', next, {
                            preserveState: false,
                            preserveScroll: true,
                        });
                    }}
                />
                <section className="hr-panel">
                    <div className="hr-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Lamaran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <strong>{a.full_name}</strong>
                                        </td>
                                        <td>{a.email ?? '—'}</td>
                                        <td>{a.phone ?? '—'}</td>
                                        <td>{a.application_count}</td>
                                        <td>
                                            <Link href={`/applicants/${a.id}`}>
                                                Detail <ArrowRight size={13} />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={5}>Belum ada profil pelamar.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ep-pagination">
                        <span>
                            {applicants.from ?? 0}–{applicants.to ?? 0} dari {applicants.total}{' '}
                            profil
                        </span>
                        <nav aria-label="Halaman profil pelamar">
                            {applicants.links.map((l, i) =>
                                l.url ? (
                                    <Link
                                        key={i}
                                        href={l.url}
                                        aria-current={l.active ? 'page' : undefined}
                                    >
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === applicants.links.length - 1
                                              ? 'Berikutnya'
                                              : l.label}
                                    </Link>
                                ) : (
                                    <span key={i}>
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === applicants.links.length - 1
                                              ? 'Berikutnya'
                                              : l.label}
                                    </span>
                                ),
                            )}
                        </nav>
                    </div>
                </section>
            </main>
        </HrShell>
    );
}
