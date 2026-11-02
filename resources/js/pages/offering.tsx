import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Download, FileCheck2, UserRoundPlus } from 'lucide-react';
import type { ReactNode } from 'react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { applicationDate as date } from '../components/application-format';
import { ProcessTimeline } from '../components/ui/process-timeline';
import type { ApplicationRow } from '../types/applications';
import '../css/employees.css';
import '../css/applications.css';
import '../css/offering.css';

type Offer = {
    department_id: number;
    position_id: number;
    employment_status: string;
    join_date: string;
    response_deadline: string;
    terms: string | null;
    status: string;
    response_date: string | null;
    response_note: string | null;
    has_evidence: boolean;
};
type Props = {
    auth: { user: HrUser };
    flash: { success: string | null };
    errors?: Record<string, string>;
    application: ApplicationRow;
    offer: Offer | null;
    identity: { full_name: string; phone: string | null };
    employee: { id: number; employee_number: string; archived: boolean } | null;
    departments: { id: number; name: string }[];
    positions: { id: number; name: string; department_id: number }[];
    statuses: { value: string; label: string }[];
    today: string;
    events: { id: number; action: string; actor: string; created_at: string }[];
};
const offerLabels: Record<string, string> = {
    pending: 'Menunggu respons',
    accepted: 'Diterima kandidat',
    declined: 'Ditolak kandidat',
    withdrawn: 'Ditarik HR',
};
const actions: Record<string, string> = {
    created: 'Penawaran dibuat',
    revised: 'Penawaran direvisi',
    responded: 'Respons dicatat',
    converted: 'Pegawai dibuat',
};
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="ep-field">
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
function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {Object.entries(errors)
                .filter(([key]) => key === 'status' || key === 'version')
                .map(([key, value]) => (
                    <p key={key} className="ep-alert" role="alert">
                        {value}
                    </p>
                ))}
        </>
    );
}
function Terms({ p }: { p: Props }) {
    const f = useForm({
        department_id: String(p.offer?.department_id ?? ''),
        position_id: String(p.offer?.position_id ?? ''),
        employment_status: p.offer?.employment_status ?? 'contract',
        join_date: p.offer?.join_date ?? '',
        response_deadline: p.offer?.response_deadline ?? '',
        terms: p.offer?.terms ?? '',
        version: p.application.version,
    });
    return (
        <form
            className="ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/applications/${p.application.id}/offering`);
            }}
        >
            <Errors errors={f.errors} />
            <div className="offer-form-grid">
                <Field label="Departemen" error={f.errors.department_id}>
                    <select
                        required
                        value={f.data.department_id}
                        onChange={(e) => {
                            f.setData('department_id', e.target.value);
                            f.setData('position_id', '');
                        }}
                    >
                        <option value="">Pilih departemen</option>
                        {p.departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Jabatan" error={f.errors.position_id}>
                    <select
                        required
                        value={f.data.position_id}
                        onChange={(e) => f.setData('position_id', e.target.value)}
                    >
                        <option value="">Pilih jabatan</option>
                        {p.positions
                            .filter((v) => String(v.department_id) === f.data.department_id)
                            .map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                    </select>
                </Field>
                <Field label="Status kerja" error={f.errors.employment_status}>
                    <select
                        required
                        value={f.data.employment_status}
                        onChange={(e) => f.setData('employment_status', e.target.value)}
                    >
                        {p.statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Batas respons (WIB)" error={f.errors.response_deadline}>
                    <input
                        type="date"
                        required
                        min={p.today}
                        value={f.data.response_deadline}
                        onChange={(e) => f.setData('response_deadline', e.target.value)}
                    />
                </Field>
                <Field label="Tanggal mulai kerja" error={f.errors.join_date}>
                    <input
                        type="date"
                        required
                        min={f.data.response_deadline || p.today}
                        value={f.data.join_date}
                        onChange={(e) => f.setData('join_date', e.target.value)}
                    />
                </Field>
            </div>
            <Field label="Ketentuan tambahan (opsional)" error={f.errors.terms}>
                <textarea
                    rows={4}
                    maxLength={5000}
                    value={f.data.terms}
                    onChange={(e) => f.setData('terms', e.target.value)}
                    placeholder="Catat ketentuan yang sudah disetujui internal perusahaan."
                />
            </Field>
            <p className="ap-muted">
                Revisi sebelum respons akan disimpan dalam audit. Gunakan ringkasan terbaru saat
                memeriksa bukti kandidat.
            </p>
            <button className="ep-primary" disabled={f.processing}>
                {f.processing
                    ? 'Menyimpan…'
                    : p.offer
                      ? 'Simpan revisi penawaran'
                      : 'Simpan penawaran'}
            </button>
        </form>
    );
}
function Response({ p }: { p: Props }) {
    const f = useForm<{
        response: string;
        response_date: string;
        response_note: string;
        evidence: File | null;
        version: number;
    }>({
        response: '',
        response_date: p.today,
        response_note: '',
        evidence: null,
        version: p.application.version,
    });
    return (
        <form
            className="ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/applications/${p.application.id}/offering/response`, {
                    forceFormData: true,
                });
            }}
        >
            <Errors errors={f.errors} />
            <Field label="Respons penawaran" error={f.errors.response}>
                <select
                    required
                    value={f.data.response}
                    onChange={(e) => f.setData('response', e.target.value)}
                >
                    <option value="">Pilih respons</option>
                    <option value="accepted">Diterima kandidat</option>
                    <option value="declined">Ditolak kandidat</option>
                    <option value="withdrawn">Ditarik oleh HR</option>
                </select>
            </Field>
            <Field label="Tanggal respons (WIB)" error={f.errors.response_date}>
                <input
                    type="date"
                    required
                    max={p.today}
                    value={f.data.response_date}
                    onChange={(e) => f.setData('response_date', e.target.value)}
                />
            </Field>
            <Field label="Catatan pemeriksaan respons" error={f.errors.response_note}>
                <textarea
                    required
                    minLength={10}
                    maxLength={2000}
                    rows={3}
                    value={f.data.response_note}
                    onChange={(e) => f.setData('response_note', e.target.value)}
                />
            </Field>
            <Field label="Bukti respons / penarikan (PDF, maks. 5 MiB)" error={f.errors.evidence}>
                <input
                    type="file"
                    required
                    accept=".pdf,application/pdf"
                    onChange={(e) => f.setData('evidence', e.target.files?.[0] ?? null)}
                />
            </Field>
            <p className="ap-muted">
                Periksa bukti terhadap penawaran terbaru. Respons disimpan sekali dan mengakhiri
                tahap seleksi: Diterima atau Ditolak.
            </p>
            <button className="ep-primary" disabled={f.processing || !f.data.evidence}>
                {f.processing ? 'Menyimpan…' : 'Catat respons dan keputusan'}
            </button>
        </form>
    );
}
function Conversion({ p }: { p: Props }) {
    const f = useForm({
        full_name: p.identity.full_name,
        phone: p.identity.phone ?? '',
        employee_number: '',
        work_email: '',
        identity_confirmed: false,
        version: p.application.version,
    });
    return (
        <form
            className="ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/applications/${p.application.id}/offering/employee`);
            }}
        >
            <Errors errors={f.errors} />
            <Field label="Nama lengkap yang dikonfirmasi" error={f.errors.full_name}>
                <input
                    required
                    maxLength={255}
                    value={f.data.full_name}
                    onChange={(e) => f.setData('full_name', e.target.value)}
                />
            </Field>
            <Field label="Telepon (opsional)" error={f.errors.phone}>
                <input
                    type="tel"
                    maxLength={32}
                    value={f.data.phone}
                    onChange={(e) => f.setData('phone', e.target.value)}
                />
            </Field>
            <Field label="Nomor pegawai" error={f.errors.employee_number}>
                <input
                    required
                    maxLength={50}
                    value={f.data.employee_number}
                    onChange={(e) => f.setData('employee_number', e.target.value)}
                />
            </Field>
            <Field label="Email kerja" error={f.errors.work_email}>
                <input
                    type="email"
                    required
                    maxLength={255}
                    value={f.data.work_email}
                    onChange={(e) => f.setData('work_email', e.target.value)}
                />
            </Field>
            <label className="offer-confirm">
                <input
                    type="checkbox"
                    required
                    checked={f.data.identity_confirmed}
                    onChange={(e) => f.setData('identity_confirmed', e.target.checked)}
                />
                <span>
                    Saya telah memeriksa identitas, bukti penerimaan dan memastikan kandidat belum
                    tercatat sebagai pegawai.
                </span>
            </label>
            {f.errors.identity_confirmed && (
                <p className="ep-error">{f.errors.identity_confirmed}</p>
            )}
            <p className="ap-muted">
                Departemen, jabatan, status kerja dan tanggal mulai mengikuti penawaran. Profil
                pelamar tetap tersimpan. Akun login tidak dibuat otomatis.
            </p>
            <button className="ep-primary" disabled={f.processing || !f.data.identity_confirmed}>
                <UserRoundPlus size={16} />
                {f.processing ? 'Membuat pegawai…' : 'Buat data pegawai'}
            </button>
        </form>
    );
}
export default function Offering(p: Props) {
    const a = p.application;
    const editable =
        a.status === 'offering' &&
        (!p.offer || p.offer.status === 'pending') &&
        (a.source !== 'public' || !!a.email_verified_at);
    const url = `/applications/${a.id}/offering`;
    return (
        <HrShell title="Penawaran & pegawai" active="recruitment" user={p.auth.user}>
            <Head title={`Penawaran · ${a.name}`} />
            <main id="hr-main" className="hr-main ep-main">
                <Link className="hr-text-button" href={`/applications/${a.id}`}>
                    <ArrowLeft size={15} /> Kembali ke lamaran
                </Link>
                <header className="hr-page-heading">
                    <div>
                        <p className="hr-eyebrow">REKRUTMEN / OFFERING</p>
                        <h1>Langkah berikutnya untuk {a.name}.</h1>
                        <p>
                            {a.job_title} · {a.status_label}
                        </p>
                    </div>
                </header>
                {p.flash.success && (
                    <p className="ep-alert" role="status">
                        {p.flash.success}
                    </p>
                )}
                {p.errors?.version && (
                    <p className="ep-alert" role="alert">
                        Data sudah berubah. Formulir dimuat ulang dengan penawaran terbaru; periksa
                        kembali sebelum menyimpan.
                    </p>
                )}
                <div className="offer-layout">
                    <div>
                        <section className="ep-card">
                            <div className="ep-section-heading">
                                <h2>Penawaran kerja</h2>
                                <p>
                                    {p.offer
                                        ? offerLabels[p.offer.status]
                                        : 'Catat penempatan dan ketentuan sebelum meminta respons.'}
                                </p>
                            </div>
                            {p.offer && (
                                <a
                                    className="hr-text-button offer-download"
                                    href={`${url}/summary`}
                                >
                                    <Download size={16} /> Unduh ringkasan PDF internal
                                </a>
                            )}
                            {editable ? (
                                <Terms key={a.version} p={p} />
                            ) : p.offer ? (
                                <>
                                    <dl className="offer-summary">
                                        {[
                                            [
                                                'Departemen',
                                                p.departments.find(
                                                    (d) => d.id === p.offer?.department_id,
                                                )?.name,
                                            ],
                                            [
                                                'Jabatan',
                                                p.positions.find(
                                                    (v) => v.id === p.offer?.position_id,
                                                )?.name,
                                            ],
                                            [
                                                'Status kerja',
                                                p.statuses.find(
                                                    (s) => s.value === p.offer?.employment_status,
                                                )?.label,
                                            ],
                                            ['Mulai kerja', date(p.offer.join_date)],
                                            ['Batas respons', date(p.offer.response_deadline)],
                                            [
                                                'Respons tercatat',
                                                p.offer.response_date
                                                    ? date(p.offer.response_date)
                                                    : '—',
                                            ],
                                        ].map(([label, value]) => (
                                            <div key={label}>
                                                <dt>{label}</dt>
                                                <dd>{value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                    <h3>Ketentuan tambahan</h3>
                                    <p className="offer-copy">
                                        {p.offer.terms || 'Tidak ada ketentuan tambahan.'}
                                    </p>
                                    {p.offer.response_note && (
                                        <>
                                            <h3>Catatan respons</h3>
                                            <p className="offer-copy">{p.offer.response_note}</p>
                                        </>
                                    )}
                                </>
                            ) : (
                                <p className="ap-muted">
                                    Penawaran dapat dibuat saat lamaran berada pada tahap Offering
                                    dan email publik telah diverifikasi.
                                </p>
                            )}
                            <p className="ep-bottom-note">
                                Periksa rincian penawaran dan simpan bukti respons kandidat. Dokumen
                                diunduh di sini, tanpa pengiriman otomatis.
                            </p>
                        </section>
                        <section className="ep-card">
                            <div className="ep-section-heading">
                                <h2>Riwayat penawaran</h2>
                                <p>
                                    Pembuatan, revisi, respons dan konversi mencatat pelaku serta
                                    waktu.
                                </p>
                            </div>
                            {p.events.length ? (
                                <ProcessTimeline
                                    items={p.events.map((e, index) => ({
                                        label: actions[e.action] ?? e.action,
                                        time: `${date(e.created_at, true)} WIB`,
                                        state:
                                            index === 0 ? ('current' as const) : ('done' as const),
                                        note: e.actor,
                                    }))}
                                />
                            ) : (
                                <p className="ap-muted">Belum ada tindakan penawaran.</p>
                            )}
                        </section>
                    </div>
                    <section className="ep-card offer-next">
                        <FileCheck2 size={22} />
                        <div className="ep-section-heading">
                            <h2>
                                {p.employee
                                    ? 'Pegawai terhubung'
                                    : p.offer?.status === 'accepted'
                                      ? 'Masuk ke direktori pegawai'
                                      : 'Respons kandidat'}
                            </h2>
                            <p>Setiap langkah memerlukan pemeriksaan HR.</p>
                        </div>
                        {p.employee ? (
                            <>
                                <p>
                                    {p.employee.employee_number}
                                    {p.employee.archived ? ' · Diarsipkan' : ''}
                                </p>
                                {!p.employee.archived || p.auth.user.role === 'admin' ? (
                                    <Link
                                        className="hr-text-button"
                                        href={`/employees/${p.employee.id}`}
                                    >
                                        Lihat data pegawai <ArrowRight size={15} />
                                    </Link>
                                ) : (
                                    <p className="ap-muted">
                                        Hubungi Admin untuk pemeriksaan pegawai yang diarsipkan.
                                    </p>
                                )}
                            </>
                        ) : p.offer?.status === 'accepted' && a.status === 'accepted' ? (
                            <Conversion key={a.version} p={p} />
                        ) : editable && p.offer ? (
                            <Response key={a.version} p={p} />
                        ) : (
                            <p className="ap-muted">
                                {p.offer?.status === 'declined' || p.offer?.status === 'withdrawn'
                                    ? 'Penawaran sudah ditutup. Konversi pegawai tidak tersedia.'
                                    : 'Simpan penawaran terlebih dahulu. Setelah itu, catat respons dan unggah buktinya di sini.'}
                            </p>
                        )}
                        {p.offer?.has_evidence && (
                            <a className="hr-text-button offer-download" href={`${url}/evidence`}>
                                <Download size={15} /> Unduh bukti respons
                            </a>
                        )}
                    </section>
                </div>
            </main>
        </HrShell>
    );
}
