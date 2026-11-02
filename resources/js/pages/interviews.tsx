import { Link, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Plus, ArrowLeft, FileText, Search } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import RecruitmentTabs from '../components/recruitment-tabs';
import { applicationDate as date } from '../components/application-format';
import '../css/employees.css';
import '../css/applications.css';

type Interview = {
    id: number;
    application_id: number;
    version: number;
    name: string;
    job_title: string;
    interviewer: string;
    starts_at: string;
    ends_at: string;
    cancelled_at: string | null;
    location?: string | null;
    meeting_url?: string | null;
    notes?: string | null;
    cancel_reason?: string | null;
    can_cancel: boolean;
    can_download: boolean;
};
type Props = {
    auth: { user: HrUser };
    flash: { success?: string };
    mode: 'index' | 'create';
    canManage: boolean;
    q?: string;
    selectedId?: number;
    defaultStart?: string;
    scope?: string;
    applications?: { id: number; name: string; job_title: string }[];
    interviewers?: { id: number; name: string }[];
    interviews?: {
        data: Interview[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
};
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
function Create(p: Props) {
    const [q, setQ] = useState(p.q ?? '');
    const f = useForm({
        application_id: String(p.selectedId ?? ''),
        interviewer_id: '',
        starts_at: p.defaultStart ?? '',
        duration: 45,
        location: '',
        meeting_url: '',
        notes: '',
    });
    return (
        <>
            <form
                className="ep-search ap-profile-search"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/interviews/create', { q });
                }}
            >
                <Search size={15} />
                <input
                    aria-label="Cari pelamar interview"
                    placeholder="Cari nama/email lamaran tahap Interview…"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                />
                <button>Cari</button>
            </form>
            <form
                className="ep-card ep-form ap-stacked"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post('/interviews');
                }}
            >
                <div className="ep-section-heading">
                    <h2>Rincian interview</h2>
                    <p>
                        Semua waktu diisi dalam WIB. Lokasi atau tautan meeting HTTPS wajib
                        tersedia.
                    </p>
                </div>
                {!p.applications?.length && (
                    <p className="ep-alert">
                        Belum ada lamaran yang cocok pada tahap Interview.{' '}
                        <Link href="/applications">Periksa pipeline kandidat.</Link>
                    </p>
                )}
                <div className="ep-form-grid">
                    <Field label="Lamaran" error={f.errors.application_id}>
                        <select
                            required
                            value={f.data.application_id}
                            onChange={(e) => f.setData('application_id', e.target.value)}
                        >
                            <option value="">Pilih lamaran (maksimal 30 hasil)</option>
                            {p.applications?.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name} · {a.job_title}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Pewawancara" error={f.errors.interviewer_id}>
                        <select
                            required
                            value={f.data.interviewer_id}
                            onChange={(e) => f.setData('interviewer_id', e.target.value)}
                        >
                            <option value="">Pilih pewawancara</option>
                            {p.interviewers?.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Mulai (WIB)" error={f.errors.starts_at}>
                        <input
                            type="datetime-local"
                            required
                            value={f.data.starts_at}
                            onChange={(e) => f.setData('starts_at', e.target.value)}
                        />
                    </Field>
                    <Field label="Durasi (menit)" error={f.errors.duration}>
                        <input
                            type="number"
                            required
                            min={15}
                            max={480}
                            value={f.data.duration}
                            onChange={(e) => f.setData('duration', Number(e.target.value))}
                        />
                    </Field>
                    <Field label="Lokasi" error={f.errors.location}>
                        <input
                            maxLength={255}
                            value={f.data.location}
                            onChange={(e) => f.setData('location', e.target.value)}
                        />
                    </Field>
                    <Field label="Tautan meeting HTTPS" error={f.errors.meeting_url}>
                        <input
                            type="url"
                            maxLength={2048}
                            placeholder="https://…"
                            value={f.data.meeting_url}
                            onChange={(e) => f.setData('meeting_url', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="Catatan internal (opsional)" error={f.errors.notes}>
                    <textarea
                        rows={3}
                        maxLength={3000}
                        value={f.data.notes}
                        onChange={(e) => f.setData('notes', e.target.value)}
                    />
                </Field>
                <div className="ep-form-footer">
                    <Link href="/interviews" className="hr-button">
                        Batal
                    </Link>
                    <button
                        className="ep-primary"
                        disabled={f.processing || !p.applications?.length}
                    >
                        {f.processing ? 'Menyimpan…' : 'Jadwalkan interview'}
                    </button>
                </div>
            </form>
        </>
    );
}
function Cancel({ item }: { item: Interview }) {
    const f = useForm({ reason: '', version: item.version });
    return (
        <details>
            <summary className="ep-archive-link">Batalkan jadwal</summary>
            <form
                className="ap-stacked ap-upload"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(`/interviews/${item.id}/cancel`);
                }}
            >
                <Field label="Alasan pembatalan" error={f.errors.reason}>
                    <textarea
                        required
                        minLength={10}
                        maxLength={1000}
                        rows={2}
                        value={f.data.reason}
                        onChange={(e) => f.setData('reason', e.target.value)}
                    />
                </Field>
                {f.errors.version && (
                    <p className="ep-error" role="alert">
                        {f.errors.version}
                    </p>
                )}
                <button className="ep-danger" disabled={f.processing}>
                    Konfirmasi pembatalan
                </button>
            </form>
        </details>
    );
}
export default function Interviews(p: Props) {
    return (
        <HrShell
            title={p.mode === 'create' ? 'Jadwalkan interview' : 'Jadwal interview'}
            active="recruitment"
            user={p.auth.user}
        >
            <main id="hr-main" className="hr-main ep-main">
                <RecruitmentTabs active="interviews" />
                {p.mode === 'create' && (
                    <Link href="/interviews" className="ep-back">
                        <ArrowLeft size={14} />
                        Kembali ke agenda
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{p.mode === 'create' ? 'Jadwalkan interview' : 'Jadwal interview'}</h1>
                        <p>
                            Waktu ditampilkan dalam WIB. Undangan diunduh, tanpa pengiriman email
                            otomatis.
                        </p>
                    </div>
                    {p.mode === 'index' && p.canManage && (
                        <Link href="/interviews/create" className="ep-primary">
                            <Plus size={15} />
                            Jadwalkan interview
                        </Link>
                    )}
                </div>
                {p.flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {p.flash.success}
                    </p>
                )}
                {p.mode === 'create' ? (
                    <Create {...p} />
                ) : (
                    <>
                        <div className="ep-tabs">
                            {[
                                ['upcoming', 'Mendatang'],
                                ['all', 'Semua riwayat'],
                                ['cancelled', 'Dibatalkan'],
                            ].map(([s, l]) => (
                                <Link
                                    key={s}
                                    href={`/interviews?scope=${s}`}
                                    aria-current={
                                        (p.scope ?? 'upcoming') === s ? 'page' : undefined
                                    }
                                >
                                    {l}
                                </Link>
                            ))}
                        </div>
                        {!p.interviews?.data.length && (
                            <section className="ep-card ep-empty">
                                <h2>Belum ada interview yang ditampilkan</h2>
                                <p>
                                    Jadwalkan dari lamaran di tahap Interview atau buka tab riwayat.
                                </p>
                            </section>
                        )}
                        {p.interviews?.data.map((i) => (
                            <section className="ep-card" key={i.id}>
                                <div className="hr-panel-title">
                                    <div>
                                        <h2>{i.name}</h2>
                                        <p>{i.job_title}</p>
                                    </div>
                                    <span>
                                        {i.cancelled_at
                                            ? 'Dibatalkan'
                                            : new Date(i.ends_at) < new Date()
                                              ? 'Selesai'
                                              : 'Terjadwal'}
                                    </span>
                                </div>
                                <dl className="ep-details">
                                    <div>
                                        <dt>Waktu (WIB)</dt>
                                        <dd>
                                            {date(i.starts_at, true)} –{' '}
                                            {new Intl.DateTimeFormat('id-ID', {
                                                timeStyle: 'short',
                                                timeZone: 'Asia/Jakarta',
                                            }).format(new Date(i.ends_at))}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Pewawancara</dt>
                                        <dd>{i.interviewer}</dd>
                                    </div>
                                    {p.canManage && (
                                        <>
                                            <div>
                                                <dt>Lokasi / meeting</dt>
                                                <dd>
                                                    {i.location ?? '—'}
                                                    {i.meeting_url && (
                                                        <p>
                                                            <a
                                                                href={i.meeting_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                Buka tautan meeting
                                                            </a>
                                                        </p>
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>Catatan internal</dt>
                                                <dd>{i.notes ?? '—'}</dd>
                                            </div>
                                        </>
                                    )}
                                </dl>
                                {p.canManage && i.cancel_reason && (
                                    <p className="ep-alert">{i.cancel_reason}</p>
                                )}
                                <div className="ep-detail-actions">
                                    <Link href={`/applications/${i.application_id}`}>
                                        Detail lamaran →
                                    </Link>
                                    {p.canManage && i.can_download && (
                                        <a
                                            className="hr-button"
                                            href={`/interviews/${i.id}/invitation`}
                                        >
                                            <FileText size={15} />
                                            Unduh undangan PDF
                                        </a>
                                    )}
                                </div>
                                {p.canManage && i.can_cancel && (
                                    <div className="ap-upload">
                                        <Cancel item={i} />
                                    </div>
                                )}
                            </section>
                        ))}
                        <div className="ep-pagination">
                            <span>
                                {p.interviews?.from ?? 0}–{p.interviews?.to ?? 0} dari{' '}
                                {p.interviews?.total ?? 0} interview
                            </span>
                            <nav aria-label="Halaman interview">
                                {p.interviews?.links.map((l, i) =>
                                    l.url ? (
                                        <Link
                                            key={i}
                                            href={l.url}
                                            aria-current={l.active ? 'page' : undefined}
                                        >
                                            {i === 0
                                                ? 'Sebelumnya'
                                                : i === p.interviews!.links.length - 1
                                                  ? 'Berikutnya'
                                                  : l.label}
                                        </Link>
                                    ) : (
                                        <span key={i}>
                                            {i === 0
                                                ? 'Sebelumnya'
                                                : i === p.interviews!.links.length - 1
                                                  ? 'Berikutnya'
                                                  : l.label}
                                        </span>
                                    ),
                                )}
                            </nav>
                        </div>
                    </>
                )}
            </main>
        </HrShell>
    );
}
