import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, Download, Plus, Search } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { applicationDate as date } from '../components/application-format';
import { StatusBadge } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';
import '../css/trainings.css';

type Training = {
    id: number;
    title: string;
    trainer: string;
    location: string;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
    participant_count: number;
    version: number;
    notes?: string | null;
};
type Participant = {
    id: number;
    employee_id: number;
    name: string;
    employee_number: string;
    archived: boolean;
    attendance: string;
    pre_test: number | null;
    post_test: number | null;
    gain: number | null;
    notes: string | null;
    version: number;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Page<T> = { data: T[]; total: number; links: PageLink[] };
type Initial = {
    title: string;
    trainer: string;
    location: string;
    starts_at: string;
    ends_at: string;
    notes: string | null;
};
type Props = {
    auth: { user: HrUser };
    flash: { success: string | null };
    errors?: Record<string, string>;
    mode: 'index' | 'create' | 'edit' | 'show' | 'evaluation';
    canManage: boolean;
    training?: Training;
    trainings?: Page<Training>;
    initial?: Initial;
    participant?: Participant;
    participants?: Page<Participant>;
    filters?: { q?: string; status?: string; employee_q?: string };
    employees?: { id: number; full_name: string; employee_number: string }[];
    materials?: { id: number; version: number; size_bytes: number; created_at: string }[];
    events?: { id: number; action: string; actor: string; created_at: string }[];
    summary?: {
        registered: number;
        evaluated?: number;
        average_pre?: number | null;
        average_post?: number | null;
        average_gain?: number | null;
    };
};
const attendanceLabels: Record<string, string> = {
    registered: 'Terdaftar',
    attended: 'Hadir',
    absent: 'Tidak hadir',
};
const eventLabels: Record<string, string> = {
    created: 'Pelatihan dibuat',
    updated: 'Jadwal diperbarui',
    enrolled: 'Peserta ditambahkan',
    evaluated: 'Evaluasi dicatat',
    material_uploaded: 'Materi diunggah',
    completed: 'Pelatihan diselesaikan',
    cancelled: 'Pelatihan dibatalkan',
};
const score = (value: number | null | undefined) =>
    value == null
        ? '—'
        : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="ep-field tr-field">
            <span>{label}</span>
            {children}
            {error && (
                <small className="ep-error" role="alert">
                    {error}
                </small>
            )}
        </label>
    );
}
function FormErrors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {['version', 'status'].map(
                (key) =>
                    errors[key] && (
                        <p key={key} className="ep-alert" role="alert">
                            {errors[key]}
                        </p>
                    ),
            )}
        </>
    );
}
function Pagination({ page }: { page?: Page<unknown> }) {
    return page && page.total > 15 ? (
        <nav className="tr-pagination" aria-label="Halaman daftar">
            {page.links.map((l, i) =>
                l.url ? (
                    <Link key={i} href={l.url} aria-current={l.active ? 'page' : undefined}>
                        {i === 0
                            ? 'Sebelumnya'
                            : i === page.links.length - 1
                              ? 'Berikutnya'
                              : l.label}
                    </Link>
                ) : (
                    <span key={i}>
                        {i === 0
                            ? 'Sebelumnya'
                            : i === page.links.length - 1
                              ? 'Berikutnya'
                              : l.label}
                    </span>
                ),
            )}
        </nav>
    ) : null;
}
function ScheduleForm({ p }: { p: Props }) {
    const f = useForm({
        title: p.initial?.title ?? '',
        trainer: p.initial?.trainer ?? '',
        location: p.initial?.location ?? '',
        starts_at: p.initial?.starts_at ?? '',
        ends_at: p.initial?.ends_at ?? '',
        notes: p.initial?.notes ?? '',
        version: p.training?.version ?? 1,
    });
    return (
        <form
            className="ep-card ap-stacked tr-schedule"
            onSubmit={(e) => {
                e.preventDefault();
                if (p.training) f.put(`/trainings/${p.training.id}`);
                else f.post('/trainings');
            }}
        >
            <FormErrors errors={f.errors} />
            <Field label="Judul pelatihan" error={f.errors.title}>
                <input
                    required
                    maxLength={255}
                    value={f.data.title}
                    onChange={(e) => f.setData('title', e.target.value)}
                />
            </Field>
            <div className="tr-form-grid">
                <Field label="Pemateri" error={f.errors.trainer}>
                    <input
                        required
                        maxLength={255}
                        value={f.data.trainer}
                        onChange={(e) => f.setData('trainer', e.target.value)}
                    />
                </Field>
                <Field label="Lokasi" error={f.errors.location}>
                    <input
                        required
                        maxLength={255}
                        value={f.data.location}
                        onChange={(e) => f.setData('location', e.target.value)}
                    />
                </Field>
                <Field label="Mulai (WIB)" error={f.errors.starts_at}>
                    <input
                        type="datetime-local"
                        required
                        value={f.data.starts_at}
                        onChange={(e) => f.setData('starts_at', e.target.value)}
                    />
                </Field>
                <Field label="Selesai (WIB)" error={f.errors.ends_at}>
                    <input
                        type="datetime-local"
                        required
                        min={f.data.starts_at}
                        value={f.data.ends_at}
                        onChange={(e) => f.setData('ends_at', e.target.value)}
                    />
                </Field>
            </div>
            <Field label="Catatan HR (opsional)" error={f.errors.notes}>
                <textarea
                    rows={3}
                    maxLength={3000}
                    value={f.data.notes}
                    onChange={(e) => f.setData('notes', e.target.value)}
                />
            </Field>
            <div className="tr-actions">
                <button className="ep-primary" disabled={f.processing}>
                    {f.processing ? 'Menyimpan…' : 'Simpan pelatihan'}
                </button>
                <Link
                    className="ep-back"
                    href={p.training ? `/trainings/${p.training.id}` : '/trainings'}
                >
                    Batal
                </Link>
            </div>
        </form>
    );
}
function Evaluation({ p, t, participant }: { p: Props; t: Training; participant: Participant }) {
    const f = useForm({
        version: participant.version,
        attendance: participant.attendance,
        pre_test: participant.pre_test?.toString() ?? '',
        post_test: participant.post_test?.toString() ?? '',
        notes: participant.notes ?? '',
    });
    return (
        <form
            className="ep-card ap-stacked tr-schedule"
            onSubmit={(e) => {
                e.preventDefault();
                f.put(`/trainings/${t.id}/participants/${participant.id}`);
            }}
        >
            <div className="ep-section-heading">
                <h2>{participant.name}</h2>
                <p>
                    {participant.employee_number}
                    {participant.archived ? ' · Pegawai diarsipkan' : ''} · {t.title}
                </p>
            </div>
            <FormErrors errors={f.errors} />
            <Field label="Kehadiran" error={f.errors.attendance}>
                <select
                    value={f.data.attendance}
                    onChange={(e) => f.setData('attendance', e.target.value)}
                >
                    {Object.entries(attendanceLabels).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </Field>
            <div className="tr-form-grid">
                <Field label="Pre-test (0–100)" error={f.errors.pre_test}>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        value={f.data.pre_test}
                        onChange={(e) => f.setData('pre_test', e.target.value)}
                    />
                </Field>
                <Field label="Post-test (0–100)" error={f.errors.post_test}>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        value={f.data.post_test}
                        onChange={(e) => f.setData('post_test', e.target.value)}
                    />
                </Field>
            </div>
            <p className="ap-muted">
                Kosongkan nilai yang belum tersedia. Post-test dicatat setelah sesi berakhir,
                peserta hadir, dan pre-test sudah diisi.
            </p>
            <Field label="Catatan evaluasi (opsional)" error={f.errors.notes}>
                <textarea
                    rows={3}
                    maxLength={2000}
                    value={f.data.notes}
                    onChange={(e) => f.setData('notes', e.target.value)}
                />
            </Field>
            <button
                className="ep-primary"
                disabled={f.processing || t.status === 'cancelled' || !p.canManage}
            >
                {f.processing ? 'Menyimpan…' : 'Simpan evaluasi'}
            </button>
        </form>
    );
}
function Enrollment({ p, t }: { p: Props; t: Training }) {
    const [q, setQ] = useState(p.filters?.employee_q ?? '');
    const f = useForm({ employee_id: '', version: t.version });
    return (
        <section className="ep-card">
            <div className="ep-section-heading">
                <h2>Tambah peserta</h2>
                <p>Pilih pegawai aktif. Maksimal 20 hasil pencarian ditampilkan.</p>
            </div>
            <form
                className="tr-search"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(
                        `/trainings/${t.id}`,
                        { employee_q: q },
                        { preserveState: false, preserveScroll: true },
                    );
                }}
            >
                <input
                    aria-label="Cari pegawai untuk pelatihan"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Nama atau nomor pegawai…"
                    maxLength={100}
                />
                <button className="hr-button" type="submit">
                    <Search size={15} />
                    Cari
                </button>
            </form>
            <form
                className="ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(`/trainings/${t.id}/participants`, { preserveScroll: true });
                }}
            >
                <FormErrors errors={f.errors} />
                <Field label="Pegawai" error={f.errors.employee_id}>
                    <select
                        required
                        value={f.data.employee_id}
                        onChange={(e) => f.setData('employee_id', e.target.value)}
                    >
                        <option value="">Pilih peserta</option>
                        {p.employees?.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.full_name} · {e.employee_number}
                            </option>
                        ))}
                    </select>
                </Field>
                {!p.employees?.length && (
                    <p className="ap-muted">
                        Tidak ada pegawai yang cocok atau semua hasil sudah terdaftar.
                    </p>
                )}
                <button className="ep-primary" disabled={f.processing || !f.data.employee_id}>
                    Tambah peserta
                </button>
            </form>
        </section>
    );
}
function Materials({ p, t }: { p: Props; t: Training }) {
    const input = useRef<HTMLInputElement>(null);
    const f = useForm<{ material: File | null }>({ material: null });
    return (
        <section className="ep-card">
            <div className="ep-section-heading">
                <h2>Materi pelatihan</h2>
                <p>PDF privat. Unggahan baru menambah versi; materi lama tetap tersimpan.</p>
            </div>
            <FormErrors errors={f.errors} />
            {p.materials?.length ? (
                p.materials.map((m, i) => (
                    <a
                        className="tr-material"
                        key={m.id}
                        href={`/trainings/${t.id}/materials/${m.id}`}
                    >
                        <Download size={17} />
                        <span>
                            Materi versi {m.version}
                            {i === 0 ? ' · Terbaru' : ''}
                            <small>
                                {date(m.created_at, true)} WIB · {Math.ceil(m.size_bytes / 1024)}{' '}
                                KiB
                            </small>
                        </span>
                    </a>
                ))
            ) : (
                <p className="ap-muted">Belum ada materi yang diunggah.</p>
            )}
            {t.status !== 'cancelled' && (
                <form
                    className="ap-stacked"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(`/trainings/${t.id}/materials`, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                f.reset();
                                if (input.current) input.current.value = '';
                            },
                        });
                    }}
                >
                    <Field label="Unggah materi (PDF, maks. 5 MiB)" error={f.errors.material}>
                        <input
                            ref={input}
                            type="file"
                            required
                            accept=".pdf,application/pdf"
                            onChange={(e) => f.setData('material', e.target.files?.[0] ?? null)}
                        />
                    </Field>
                    <button className="ep-primary" disabled={f.processing || !f.data.material}>
                        {f.processing ? 'Mengunggah…' : 'Unggah materi'}
                    </button>
                </form>
            )}
        </section>
    );
}
function StatusForm({ t }: { t: Training }) {
    const f = useForm({ version: t.version, status: '', reason: '' });
    return (
        <section className="ep-card">
            <div className="ep-section-heading">
                <h2>Status pelatihan</h2>
                <p>
                    Pelatihan dapat ditandai selesai setelah sesi berakhir. Pembatalan
                    mempertahankan riwayat.
                </p>
            </div>
            <form
                className="ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(`/trainings/${t.id}/status`, { preserveScroll: true });
                }}
            >
                <FormErrors errors={f.errors} />
                <Field label="Ubah status" error={f.errors.status}>
                    <select
                        required
                        value={f.data.status}
                        onChange={(e) => f.setData('status', e.target.value)}
                    >
                        <option value="">Pilih status</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </Field>
                {f.data.status === 'cancelled' && (
                    <Field label="Alasan pembatalan" error={f.errors.reason}>
                        <textarea
                            required
                            minLength={10}
                            maxLength={1000}
                            rows={3}
                            value={f.data.reason}
                            onChange={(e) => f.setData('reason', e.target.value)}
                        />
                    </Field>
                )}
                <button className="ep-primary" disabled={f.processing || !f.data.status}>
                    Simpan status
                </button>
            </form>
        </section>
    );
}
function Directory({ p }: { p: Props }) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [status, setStatus] = useState(p.filters?.status ?? '');
    return (
        <>
            <form
                className="tr-list-filters"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/trainings', { q, status });
                }}
            >
                <input
                    aria-label="Cari pelatihan"
                    placeholder="Cari judul atau pemateri…"
                    maxLength={100}
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                />
                <select
                    aria-label="Status pelatihan"
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                >
                    <option value="">Semua status</option>
                    <option value="scheduled">Terjadwal</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <button className="ep-primary">
                    <Search size={15} />
                    Cari
                </button>
            </form>
            <section className="hr-panel">
                <div className="hr-table-scroll">
                    <table>
                        <caption className="sr-only">Daftar pelatihan</caption>
                        <thead>
                            <tr>
                                <th>Pelatihan</th>
                                <th>Pemateri</th>
                                <th>Jadwal (WIB)</th>
                                <th>Peserta</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {p.trainings?.data.map((t) => (
                                <tr key={t.id}>
                                    <td>
                                        <strong>{t.title}</strong>
                                        <small className="tr-sub">{t.location}</small>
                                    </td>
                                    <td>{t.trainer}</td>
                                    <td>
                                        {date(t.starts_at, true)}
                                        <small className="tr-sub">
                                            s.d. {date(t.ends_at, true)}
                                        </small>
                                    </td>
                                    <td>{t.participant_count}</td>
                                    <td>
                                        <StatusBadge
                                            variant={
                                                t.status === 'completed'
                                                    ? 'success'
                                                    : t.status === 'cancelled'
                                                      ? 'failed'
                                                      : 'progress'
                                            }
                                        >
                                            {t.status_label}
                                        </StatusBadge>
                                    </td>
                                    <td>
                                        <Link
                                            className="hr-text-button"
                                            href={`/trainings/${t.id}`}
                                        >
                                            Detail <ArrowRight size={14} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {!p.trainings?.data.length && (
                                <tr>
                                    <td colSpan={6}>
                                        Belum ada pelatihan yang sesuai.{' '}
                                        {p.canManage &&
                                            'Tambahkan jadwal untuk mulai mencatat pelatihan tim.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
            <Pagination page={p.trainings} />
        </>
    );
}
export default function Trainings(p: Props) {
    const t = p.training;
    const title =
        p.mode === 'index'
            ? 'Pelatihan'
            : p.mode === 'create'
              ? 'Tambah pelatihan'
              : p.mode === 'edit'
                ? 'Ubah pelatihan'
                : p.mode === 'evaluation'
                  ? 'Evaluasi peserta'
                  : (t?.title ?? 'Pelatihan');
    return (
        <HrShell title={title} active="training" user={p.auth.user}>
            <Head title={title} />
            <main id="hr-main" className="hr-main ep-main tr-page">
                {p.mode !== 'index' && (
                    <Link
                        className="ep-back"
                        href={p.mode === 'evaluation' && t ? `/trainings/${t.id}` : '/trainings'}
                    >
                        <ArrowLeft size={14} />
                        {p.mode === 'evaluation' ? 'Kembali ke pelatihan' : 'Semua pelatihan'}
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {p.mode === 'index'
                                ? 'Jadwal belajar, materi, dan perkembangan peserta dalam satu tempat.'
                                : t
                                  ? `${t.trainer} · ${t.location}`
                                  : 'Atur jadwal dan pemateri. Peserta serta materi dapat ditambahkan setelah disimpan.'}
                        </p>
                    </div>
                    {p.mode === 'index' && p.canManage && (
                        <Link className="ep-primary" href="/trainings/create">
                            <Plus size={16} />
                            Tambah pelatihan
                        </Link>
                    )}
                    {p.mode === 'show' && p.canManage && t?.status === 'scheduled' && (
                        <Link className="hr-button" href={`/trainings/${t.id}/edit`}>
                            Ubah jadwal
                        </Link>
                    )}
                </div>
                {p.flash.success && (
                    <p className="ep-alert" role="status">
                        {p.flash.success}
                    </p>
                )}
                {p.errors?.version && (
                    <p className="ep-alert" role="alert">
                        Data berubah. Formulir dimuat ulang; periksa nilai terbaru sebelum
                        menyimpan.
                    </p>
                )}
                {p.mode === 'index' && <Directory p={p} />}
                {(p.mode === 'create' || p.mode === 'edit') && (
                    <ScheduleForm key={t?.version ?? 'new'} p={p} />
                )}
                {p.mode === 'evaluation' && t && p.participant && (
                    <Evaluation
                        key={p.participant.version}
                        p={p}
                        t={t}
                        participant={p.participant}
                    />
                )}
                {p.mode === 'show' && t && (
                    <>
                        <section className="tr-session">
                            <div>
                                <span className="tr-label">Jadwal (WIB)</span>
                                <p>
                                    {date(t.starts_at, true)} – {date(t.ends_at, true)}
                                </p>
                            </div>
                            <StatusBadge
                                variant={
                                    t.status === 'completed'
                                        ? 'success'
                                        : t.status === 'cancelled'
                                          ? 'failed'
                                          : 'progress'
                                }
                            >
                                {t.status_label}
                            </StatusBadge>
                        </section>
                        <section className="tr-metrics" aria-label="Ringkasan pelatihan">
                            <div>
                                <span>Peserta terdaftar</span>
                                <strong>{p.summary?.registered ?? 0}</strong>
                            </div>
                            {p.canManage && (
                                <>
                                    <div>
                                        <span>Evaluasi lengkap</span>
                                        <strong>{p.summary?.evaluated ?? 0}</strong>
                                    </div>
                                    <div>
                                        <span>Rata-rata pre → post</span>
                                        <strong>
                                            {score(p.summary?.average_pre)} →{' '}
                                            {score(p.summary?.average_post)}
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Rata-rata peningkatan</span>
                                        <strong>
                                            {p.summary?.average_gain == null
                                                ? '—'
                                                : `${p.summary.average_gain > 0 ? '+' : ''}${score(p.summary.average_gain)} poin`}
                                        </strong>
                                    </div>
                                </>
                            )}
                        </section>
                        {p.canManage ? (
                            <>
                                <p className="tr-caption">
                                    Rata-rata hanya memakai peserta hadir dengan pre-test dan
                                    post-test lengkap. Nilai kosong tidak dihitung; selisih
                                    dinyatakan dalam poin.
                                </p>
                                <div className="tr-layout">
                                    <div>
                                        <section className="hr-panel">
                                            <div className="tr-panel-heading">
                                                <h2>Peserta & evaluasi</h2>
                                                <p>Kehadiran dan nilai tersimpan per peserta.</p>
                                            </div>
                                            <div className="hr-table-scroll">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>Pegawai</th>
                                                            <th>Kehadiran</th>
                                                            <th>Pre</th>
                                                            <th>Post</th>
                                                            <th>Selisih</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {p.participants?.data.map((person) => (
                                                            <tr key={person.id}>
                                                                <td>
                                                                    <strong>{person.name}</strong>
                                                                    <small className="tr-sub">
                                                                        {person.employee_number}
                                                                        {person.archived
                                                                            ? ' · Diarsipkan'
                                                                            : ''}
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    {
                                                                        attendanceLabels[
                                                                            person.attendance
                                                                        ]
                                                                    }
                                                                </td>
                                                                <td>{score(person.pre_test)}</td>
                                                                <td>{score(person.post_test)}</td>
                                                                <td>
                                                                    {person.gain == null
                                                                        ? '—'
                                                                        : `${person.gain > 0 ? '+' : ''}${score(person.gain)}`}
                                                                </td>
                                                                <td>
                                                                    {t.status !== 'cancelled' ? (
                                                                        <Link
                                                                            className="hr-text-button"
                                                                            href={`/trainings/${t.id}/participants/${person.id}/edit`}
                                                                        >
                                                                            Evaluasi
                                                                        </Link>
                                                                    ) : (
                                                                        '—'
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                        {!p.participants?.data.length && (
                                                            <tr>
                                                                <td colSpan={6}>
                                                                    Belum ada peserta. Tambahkan
                                                                    pegawai dari form di samping.
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                            <Pagination page={p.participants} />
                                        </section>
                                        {t.notes && (
                                            <section className="ep-card">
                                                <h2>Catatan HR</h2>
                                                <p className="tr-copy">{t.notes}</p>
                                            </section>
                                        )}
                                        <section className="ep-card">
                                            <div className="ep-section-heading">
                                                <h2>Riwayat pelatihan</h2>
                                                <p>30 tindakan terbaru, dengan pelaku dan waktu.</p>
                                            </div>
                                            <ProcessTimeline
                                                items={(p.events ?? []).map((e, i) => ({
                                                    label: eventLabels[e.action] ?? e.action,
                                                    time: `${date(e.created_at, true)} WIB`,
                                                    note: e.actor,
                                                    state:
                                                        i === 0
                                                            ? ('current' as const)
                                                            : ('done' as const),
                                                }))}
                                            />
                                        </section>
                                    </div>
                                    <aside>
                                        {t.status === 'scheduled' && (
                                            <Enrollment key={`enroll-${t.version}`} p={p} t={t} />
                                        )}
                                        <Materials p={p} t={t} />
                                        {t.status === 'scheduled' && (
                                            <StatusForm key={`status-${t.version}`} t={t} />
                                        )}
                                    </aside>
                                </div>
                            </>
                        ) : (
                            <section className="ep-card">
                                <h2>Jadwal pelatihan</h2>
                                <p className="tr-copy">
                                    Materi, daftar peserta dan evaluasi dikelola oleh Admin atau
                                    Staf HR.
                                </p>
                            </section>
                        )}
                    </>
                )}
            </main>
        </HrShell>
    );
}
