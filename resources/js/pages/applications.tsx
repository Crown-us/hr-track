import { Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, FileText, Plus, Search } from 'lucide-react';
import HrShell from '../components/hr-shell';
import RecruitmentTabs from '../components/recruitment-tabs';
import { StatusBadge } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import {
    applicationDate as date,
    applicationTone as tones,
} from '../components/application-format';
import type { ApplicationProps, ApplicationRow, Stage } from '../types/applications';
import { initials } from '../data/hr-preview';
import '../css/employees.css';
import '../css/applications.css';

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
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

function CreateForm(p: ApplicationProps) {
    const [q, setQ] = useState(p.applicantSearch ?? '');
    const f = useForm<{
        mode: 'new' | 'existing';
        full_name: string;
        email: string;
        phone: string;
        applicant_id: string;
        vacancy_id: string;
        application_date: string;
        notes: string;
        cv: File | null;
    }>({
        mode: p.existingMode ? 'existing' : 'new',
        full_name: '',
        email: '',
        phone: '',
        applicant_id: String(p.selectedApplicant?.id ?? ''),
        vacancy_id: '',
        application_date: p.today ?? '',
        notes: '',
        cv: null,
    });
    const people =
        p.selectedApplicant && !p.applicants?.some((a) => a.id === p.selectedApplicant?.id)
            ? [p.selectedApplicant, ...(p.applicants ?? [])]
            : (p.applicants ?? []);
    return (
        <>
            <div className="ep-tabs ap-form-tabs">
                {(['new', 'existing'] as const).map((mode) => (
                    <button
                        key={mode}
                        type="button"
                        className={f.data.mode === mode ? 'is-active' : ''}
                        onClick={() => f.setData('mode', mode)}
                    >
                        {mode === 'new' ? 'Pelamar baru' : 'Pelamar terdaftar'}
                    </button>
                ))}
            </div>
            {f.data.mode === 'existing' && (
                <form
                    className="ep-search ap-profile-search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/applications/create', { q, mode: 'existing' });
                    }}
                >
                    <Search size={16} />
                    <input
                        aria-label="Cari profil pelamar"
                        placeholder="Cari nama atau email profil…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button>Cari profil</button>
                </form>
            )}
            <form
                className="ep-card ep-form ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post('/applications', { forceFormData: true });
                }}
            >
                <div className="ep-section-heading">
                    <h2>Catat lamaran</h2>
                    <p>
                        Lamaran baru dimulai pada tahap Screening. CV tersimpan pada profil pelamar.
                    </p>
                </div>
                {!p.vacancies?.length && (
                    <p className="ep-alert">
                        Belum ada lowongan dalam periode penerimaan.{' '}
                        <Link href="/vacancies">Kelola lowongan terlebih dahulu.</Link>
                    </p>
                )}
                <div className="ep-form-grid">
                    {f.data.mode === 'new' ? (
                        (['full_name', 'email', 'phone'] as const).map((key) => (
                            <Field
                                key={key}
                                label={
                                    key === 'full_name'
                                        ? 'Nama lengkap'
                                        : key === 'email'
                                          ? 'Email pelamar'
                                          : 'Telepon (opsional)'
                                }
                                error={f.errors[key]}
                            >
                                <input
                                    required={key !== 'phone'}
                                    type={
                                        key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'
                                    }
                                    maxLength={key === 'phone' ? 32 : 255}
                                    value={f.data[key]}
                                    onChange={(e) => f.setData(key, e.target.value)}
                                />
                            </Field>
                        ))
                    ) : (
                        <Field label="Profil pelamar" error={f.errors.applicant_id}>
                            <select
                                required
                                value={f.data.applicant_id}
                                onChange={(e) => f.setData('applicant_id', e.target.value)}
                            >
                                <option value="">Pilih profil (maksimal 20 hasil)</option>
                                {people.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.full_name} · {a.email}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Lowongan tujuan" error={f.errors.vacancy_id}>
                        <select
                            required
                            value={f.data.vacancy_id}
                            onChange={(e) => f.setData('vacancy_id', e.target.value)}
                        >
                            <option value="">Pilih lowongan aktif</option>
                            {p.vacancies?.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.job_title}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Tanggal lamaran" error={f.errors.application_date}>
                        <input
                            type="date"
                            required
                            max={p.today}
                            value={f.data.application_date}
                            onChange={(e) => f.setData('application_date', e.target.value)}
                        />
                    </Field>
                    <Field label="CV PDF (opsional, maksimal 5 MiB)" error={f.errors.cv}>
                        <input
                            type="file"
                            accept=".pdf,application/pdf"
                            onChange={(e) => f.setData('cv', e.target.files?.[0] ?? null)}
                        />
                    </Field>
                </div>
                <Field label="Catatan HR (opsional)" error={f.errors.notes}>
                    <textarea
                        rows={4}
                        maxLength={3000}
                        value={f.data.notes}
                        onChange={(e) => f.setData('notes', e.target.value)}
                    />
                </Field>
                {f.progress && <p role="status">Mengunggah: {f.progress.percentage}%</p>}
                <div className="ep-form-footer">
                    <Link className="hr-button" href="/applications">
                        Batal
                    </Link>
                    <button className="ep-primary" disabled={f.processing || !p.vacancies?.length}>
                        {f.processing ? 'Menyimpan…' : 'Simpan lamaran'}
                        <ArrowRight size={15} />
                    </button>
                </div>
            </form>
        </>
    );
}

function Directory(p: ApplicationProps) {
    const filters = p.filters ?? {};
    const [q, setQ] = useState(filters.q ?? '');
    const board = filters.view !== 'list';
    const update = (data: Record<string, string>) =>
        router.get(
            '/applications',
            { ...filters, q, ...data, page: 1 },
            { preserveState: false, preserveScroll: true },
        );
    const rows = p.applications?.data ?? [];
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
                        aria-label="Cari pelamar"
                        placeholder="Cari nama, email, atau posisi…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button>Cari</button>
                </form>
                <select
                    aria-label="Filter lowongan"
                    value={filters.vacancy_id ?? ''}
                    onChange={(e) => update({ vacancy_id: e.target.value })}
                >
                    <option value="">Semua lowongan</option>
                    {p.vacancies?.map((v) => (
                        <option key={v.id} value={v.id}>
                            {v.job_title}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Filter tahap"
                    value={filters.status ?? ''}
                    onChange={(e) => update({ status: e.target.value })}
                >
                    <option value="">Semua tahap</option>
                    {p.stages.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
                <button
                    className="hr-button"
                    onClick={() => update({ view: board ? 'list' : 'board' })}
                >
                    {board ? 'Daftar' : 'Board'}
                </button>
            </div>
            <p className="ap-result-note">
                {p.applications?.total ?? 0} lamaran cocok.{' '}
                {board
                    ? 'Board menampilkan halaman aktif; jumlah tahap mencakup seluruh hasil filter.'
                    : ''}
            </p>
            {board ? (
                <div className="hr-board-frame">
                    <div className="hr-board">
                        {p.stages.map((s) => (
                            <section className="hr-board-column" key={s.value} aria-label={s.label}>
                                <div className="hr-column-heading">
                                    <h2>{s.label}</h2>
                                    <span>{p.counts?.[s.value] ?? 0}</span>
                                </div>
                                {rows
                                    .filter((a) => a.status === s.value)
                                    .map((a) => (
                                        <Link
                                            className="hr-candidate-card ap-card-link"
                                            key={a.id}
                                            href={`/applications/${a.id}`}
                                        >
                                            <div className="hr-card-heading">
                                                <span className="hr-avatar">
                                                    {initials(a.name)}
                                                </span>
                                                <span>#{a.id}</span>
                                                <ArrowRight size={14} />
                                            </div>
                                            <h3>{a.name}</h3>
                                            <p>{a.job_title}</p>
                                            <p>{date(a.application_date)}</p>
                                            <div className="hr-card-bottom">
                                                <StatusBadge variant={tones[a.status]}>
                                                    {a.status_label}
                                                </StatusBadge>
                                            </div>
                                        </Link>
                                    ))}
                                {!rows.some((a) => a.status === s.value) && (
                                    <p className="hr-column-empty">
                                        Belum ada lamaran di tahap ini pada halaman aktif.
                                    </p>
                                )}
                            </section>
                        ))}
                    </div>
                </div>
            ) : (
                <section className="hr-panel">
                    <div className="hr-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pelamar</th>
                                    <th>Lowongan</th>
                                    <th>Tanggal</th>
                                    <th>Tahap</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <strong>{a.name}</strong>
                                            <small>{a.email}</small>
                                        </td>
                                        <td>{a.job_title}</td>
                                        <td>{date(a.application_date)}</td>
                                        <td>
                                            <StatusBadge variant={tones[a.status]}>
                                                {a.status_label}
                                            </StatusBadge>
                                        </td>
                                        <td>
                                            <Link href={`/applications/${a.id}`}>Detail</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!rows.length && <p className="hr-empty">Belum ada lamaran yang cocok.</p>}
                </section>
            )}
            <div className="ep-pagination">
                <span>
                    {p.applications?.from ?? 0}–{p.applications?.to ?? 0} dari{' '}
                    {p.applications?.total ?? 0} lamaran
                </span>
                <nav aria-label="Halaman lamaran">
                    {p.applications?.links.map((l, i) =>
                        l.url ? (
                            <Link key={i} href={l.url} aria-current={l.active ? 'page' : undefined}>
                                {i === 0
                                    ? 'Sebelumnya'
                                    : i === p.applications!.links.length - 1
                                      ? 'Berikutnya'
                                      : l.label}
                            </Link>
                        ) : (
                            <span key={i}>
                                {i === 0
                                    ? 'Sebelumnya'
                                    : i === p.applications!.links.length - 1
                                      ? 'Berikutnya'
                                      : l.label}
                            </span>
                        ),
                    )}
                </nav>
            </div>
        </>
    );
}

function StatusForm({ a, stages }: { a: ApplicationRow; stages: Stage[] }) {
    const f = useForm({ status: '', note: '', version: a.version });
    if (a.source === 'public' && !a.email_verified_at) {
        return (
            <section className="ep-card">
                <h2>Tindak lanjut</h2>
                <p>Menunggu pelamar memverifikasi email sebelum tahap dapat diubah.</p>
            </section>
        );
    }
    return (
        <section className="ep-card">
            <div className="ep-section-heading">
                <h2>Tindak lanjut</h2>
                <p>Perubahan tahap mencatat pelaku dan waktu.</p>
            </div>
            {stages.length ? (
                <form
                    className="ap-stacked"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.transform((d) => ({ ...d, version: a.version }));
                        f.post(`/applications/${a.id}/status`, {
                            onSuccess: () => f.reset('status', 'note'),
                        });
                    }}
                >
                    <Field label="Tahap berikutnya" error={f.errors.status}>
                        <select
                            required
                            value={f.data.status}
                            onChange={(e) => f.setData('status', e.target.value)}
                        >
                            <option value="">Pilih tahap</option>
                            {stages.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Catatan perubahan (opsional)" error={f.errors.note}>
                        <textarea
                            rows={3}
                            maxLength={1000}
                            value={f.data.note}
                            onChange={(e) => f.setData('note', e.target.value)}
                        />
                    </Field>
                    {f.errors.version && (
                        <p className="ep-error" role="alert">
                            {f.errors.version}{' '}
                            <Link href={`/applications/${a.id}`}>Muat ulang</Link>
                        </p>
                    )}
                    <button className="ep-primary" disabled={f.processing || !f.data.status}>
                        {f.processing ? 'Menyimpan…' : 'Perbarui tahap'}
                    </button>
                </form>
            ) : (
                <p className="ap-muted">
                    Lamaran berada pada tahap akhir. Riwayat tetap tersimpan.
                </p>
            )}
        </section>
    );
}

function Documents({
    a,
    documents = [],
}: {
    a: ApplicationRow;
    documents: ApplicationProps['documents'];
}) {
    const input = useRef<HTMLInputElement>(null);
    const f = useForm<{ cv: File | null }>({ cv: null });
    return (
        <section className="ep-card">
            <div className="ep-section-heading">
                <h2>CV pelamar</h2>
                <p>
                    CV pengajuan publik ditampilkan pada lamaran terkait. Versi yang diunggah tim HR
                    dapat dipakai lintas lamaran.
                </p>
            </div>
            {documents.length ? (
                documents.map((d, i) => (
                    <a
                        className="ap-document"
                        key={d.id}
                        href={`/applications/${a.id}/documents/${d.id}`}
                    >
                        <FileText size={17} />
                        <span>
                            CV #{d.id}
                            {i === 0 ? ' · Terbaru' : ''}
                            <small>
                                {date(d.created_at, true)} WIB · {Math.ceil(d.size_bytes / 1024)}{' '}
                                KiB
                            </small>
                        </span>
                        <ArrowRight size={14} />
                    </a>
                ))
            ) : (
                <p className="ap-muted">Belum ada CV.</p>
            )}
            <form
                className="ap-stacked ap-upload"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(`/applications/${a.id}/cv`, {
                        forceFormData: true,
                        onSuccess: () => {
                            f.reset();
                            if (input.current) input.current.value = '';
                        },
                    });
                }}
            >
                <Field label="Tambah versi CV (PDF, maksimal 5 MiB)" error={f.errors.cv}>
                    <input
                        ref={input}
                        type="file"
                        required
                        accept=".pdf,application/pdf"
                        onChange={(e) => f.setData('cv', e.target.files?.[0] ?? null)}
                    />
                </Field>
                <button className="hr-button" disabled={f.processing || !f.data.cv}>
                    {f.processing ? 'Mengunggah…' : 'Unggah CV'}
                </button>
            </form>
        </section>
    );
}

export default function Applications(p: ApplicationProps) {
    const a = p.application;
    const title =
        p.mode === 'create'
            ? 'Tambah lamaran'
            : p.mode === 'show'
              ? 'Detail lamaran'
              : 'Pipeline kandidat';
    return (
        <HrShell title={title} active="recruitment" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                <RecruitmentTabs active="pipeline" />
                {p.mode !== 'index' && (
                    <Link className="ep-back" href="/applications">
                        <ArrowLeft size={14} />
                        Kembali ke pipeline
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {a
                                ? `${a.name} · ${a.job_title}`
                                : 'Ikuti perjalanan pelamar, dari berkas pertama hingga keputusan akhir.'}
                        </p>
                    </div>
                    {p.mode === 'index' && p.canManage && (
                        <Link className="ep-primary" href="/applications/create">
                            <Plus size={16} />
                            Tambah lamaran
                        </Link>
                    )}
                </div>
                {p.flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {p.flash.success}
                    </p>
                )}
                {p.mode === 'index' && <Directory key={JSON.stringify(p.filters)} {...p} />}
                {p.mode === 'create' && (
                    <CreateForm key={`${p.applicantSearch}-${p.selectedApplicant?.id}`} {...p} />
                )}
                {p.mode === 'show' && a && (
                    <div className="ap-detail-grid">
                        <div>
                            <section className="ep-card">
                                <div className="ep-profile-head">
                                    <span className="hr-avatar hr-avatar-large">
                                        {initials(a.name)}
                                    </span>
                                    <div>
                                        <h2>{a.name}</h2>
                                        <p>{a.job_title}</p>
                                    </div>
                                    <StatusBadge variant={tones[a.status]}>
                                        {a.status_label}
                                    </StatusBadge>
                                </div>
                                <dl className="ep-details">
                                    <div>
                                        <dt>Email</dt>
                                        <dd>{a.email}</dd>
                                    </div>
                                    <div>
                                        <dt>Tanggal lamaran</dt>
                                        <dd>{date(a.application_date)}</dd>
                                    </div>
                                    {p.canManage && (
                                        <div>
                                            <dt>Telepon</dt>
                                            <dd>{a.phone ?? '—'}</dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt>Lowongan</dt>
                                        <dd>
                                            <Link href={`/vacancies/${a.vacancy_id}`}>
                                                {a.job_title}
                                            </Link>
                                        </dd>
                                    </div>
                                </dl>
                                {p.canManage && (
                                    <>
                                        {a.source === 'public' && (
                                            <div className="ep-alert">
                                                <strong>
                                                    Pengajuan publik ·{' '}
                                                    {a.email_verified_at
                                                        ? 'email terverifikasi'
                                                        : 'menunggu verifikasi email'}
                                                </strong>
                                                <p>
                                                    Nama yang diajukan: {a.submitted_name ?? a.name}
                                                    <br />
                                                    Kontak yang diajukan: {a.submitted_phone ?? '—'}
                                                </p>
                                                <p>
                                                    {a.email_verified_at
                                                        ? 'Akses email telah dikonfirmasi. Tetap periksa identitas dan CV; profil terdaftar tidak diubah otomatis.'
                                                        : 'Tahap seleksi baru dapat dilanjutkan setelah pelamar memverifikasi email. Profil terdaftar tidak diubah otomatis.'}
                                                </p>
                                            </div>
                                        )}
                                        <div className="ap-private-note">
                                            <h3>Catatan HR</h3>
                                            <p>{a.notes || 'Belum ada catatan.'}</p>
                                        </div>
                                        <Link
                                            className="hr-text-button"
                                            href={`/applications/create?applicant_id=${a.applicant_id}`}
                                        >
                                            Tambah lamaran untuk pelamar ini{' '}
                                            <ArrowRight size={14} />
                                        </Link>
                                    </>
                                )}
                            </section>
                            <section className="ep-card">
                                <div className="ep-section-heading">
                                    <h2>Riwayat lamaran</h2>
                                    <p>Tercatat otomatis dari tindakan tim HR.</p>
                                </div>
                                <ProcessTimeline
                                    items={(p.histories ?? []).map((h, i) => ({
                                        label: h.label,
                                        time: `${date(h.created_at, true)} WIB`,
                                        state:
                                            i === (p.histories?.length ?? 0) - 1
                                                ? ('current' as const)
                                                : ('done' as const),
                                        note: `${h.actor}${h.note ? ` · ${h.note}` : ''}`,
                                    }))}
                                />
                            </section>
                        </div>
                        <div>
                            {p.canManage ? (
                                <>
                                    {a.status === 'interview' && (
                                        <p className="ep-alert">
                                            <Link
                                                href={`/interviews/create?application_id=${a.id}`}
                                            >
                                                Jadwalkan interview untuk pelamar ini →
                                            </Link>
                                        </p>
                                    )}
                                    {(a.status === 'offering' ||
                                        a.status === 'accepted' ||
                                        a.status === 'rejected') && (
                                        <p className="ep-alert">
                                            <Link href={`/applications/${a.id}/offering`}>
                                                Penawaran, respons & data pegawai →
                                            </Link>
                                        </p>
                                    )}
                                    {a.status !== 'offering' && (
                                        <StatusForm a={a} stages={p.nextStages ?? []} />
                                    )}
                                    <Documents a={a} documents={p.documents} />
                                </>
                            ) : (
                                <section className="ep-card">
                                    <h2>Akses pembaca</h2>
                                    <p className="ap-muted">
                                        CV, catatan privat dan perubahan tahap hanya tersedia bagi
                                        Admin/Staf HR.
                                    </p>
                                </section>
                            )}
                        </div>
                    </div>
                )}
                <p className="ep-bottom-note">
                    Data pelamar dan lamaran tersimpan di sistem. Jadwal dan undangan tersedia pada
                    tab Jadwal interview.
                </p>
            </main>
        </HrShell>
    );
}
