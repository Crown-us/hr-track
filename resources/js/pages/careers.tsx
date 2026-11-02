import { PublicHeader } from '../components/public-header';
import { careerDate } from '../lib/career-date';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight, ArrowUpRight, Check, Search } from 'lucide-react';
import { CareerJobs, type PublicJob } from '../components/career-jobs';
import '../css/careers.css';
type Props = {
    mode: 'index' | 'show' | 'apply' | 'thanks' | 'verify' | 'verified' | 'resend';
    confirmationUrl?: string;
    vacancy?: PublicJob;
    vacancies?: {
        data: PublicJob[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters?: { q?: string; department_id?: string; type?: string };
    departments?: { id: number; name: string }[];
    types?: { value: string; label: string }[];
};
function Confirmation({ url }: { url: string }) {
    const form = useForm({});
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url);
            }}
        >
            <button className="taste-button" disabled={form.processing}>
                {form.processing ? 'Memverifikasi…' : 'Ya, verifikasi email lamaran saya'}
            </button>
        </form>
    );
}
function Resend() {
    const form = useForm({ email: '' });
    return (
        <section className="career-apply career-resend">
            <h1>Kirim ulang verifikasi.</h1>
            <p>
                Masukkan email yang digunakan melamar. Untuk pengajuan yang masih menunggu
                verifikasi, tautan baru dapat dikirim setelah jeda 10 menit. Periksa juga folder
                spam.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/karier/verifikasi/kirim-ulang');
                }}
            >
                <Field label="Email lamaran" error={form.errors.email}>
                    <input
                        type="email"
                        autoComplete="email"
                        required
                        maxLength={255}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>
                <button className="taste-button" disabled={form.processing}>
                    {form.processing ? 'Memproses…' : 'Kirim tautan verifikasi'}
                </button>
            </form>
        </section>
    );
}
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="career-field">
            <span>{label}</span>
            {children}
            {error && (
                <small className="career-error" role="alert">
                    {error}
                </small>
            )}
        </label>
    );
}
function Apply({ job }: { job: PublicJob }) {
    const f = useForm<{
        full_name: string;
        email: string;
        phone: string;
        cv: File | null;
        consent: boolean;
        website: string;
        submission: string;
    }>({
        full_name: '',
        email: '',
        phone: '',
        cv: null,
        consent: false,
        website: '',
        submission: '',
    });
    return (
        <form
            className="career-apply"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/karier/${job.id}/lamar`, {
                    forceFormData: true,
                    onSuccess: () => f.reset(),
                });
            }}
        >
            <h2>Perkenalkan diri Anda.</h2>
            <p>Isi kontak yang dapat dihubungi dan lampirkan CV terbaru.</p>
            {f.errors.submission && (
                <p className="career-error" role="alert">
                    {f.errors.submission}
                </p>
            )}
            <div className="career-form-grid">
                {(['full_name', 'email', 'phone'] as const).map((field) => (
                    <Field
                        key={field}
                        label={
                            field === 'full_name'
                                ? 'Nama lengkap'
                                : field === 'email'
                                  ? 'Email'
                                  : 'Telepon (opsional)'
                        }
                        error={f.errors[field]}
                    >
                        <input
                            name={field}
                            type={field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
                            autoComplete={
                                field === 'full_name' ? 'name' : field === 'email' ? 'email' : 'tel'
                            }
                            required={field !== 'phone'}
                            maxLength={field === 'phone' ? 32 : 255}
                            value={f.data[field]}
                            onChange={(e) => f.setData(field, e.target.value)}
                        />
                    </Field>
                ))}
            </div>
            <Field label="CV PDF · maksimal 5 MiB" error={f.errors.cv}>
                <input
                    name="cv"
                    type="file"
                    required
                    accept=".pdf,application/pdf"
                    onChange={(e) => f.setData('cv', e.target.files?.[0] ?? null)}
                />
                <small>CV disimpan privat dan diakses oleh tim HR yang berwenang.</small>
            </Field>
            <div className="career-trap" aria-hidden="true">
                <label>
                    Situs web
                    <input
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        value={f.data.website}
                        onChange={(e) => f.setData('website', e.target.value)}
                    />
                </label>
            </div>
            <label className="career-consent">
                <input
                    type="checkbox"
                    required
                    checked={f.data.consent}
                    onChange={(e) => f.setData('consent', e.target.checked)}
                />
                <span>
                    Saya memahami bahwa data dan CV yang saya kirim akan disimpan untuk ditinjau tim
                    HR dalam proses lamaran ini.
                </span>
            </label>
            {f.errors.consent && (
                <p className="career-error" role="alert">
                    {f.errors.consent}
                </p>
            )}
            {f.progress && <p role="status">Mengunggah CV: {f.progress.percentage}%</p>}
            <button className="taste-button" disabled={f.processing}>
                {f.processing ? 'Mengirim…' : 'Kirim lamaran'}
                <ArrowRight size={17} aria-hidden="true" />
            </button>
            <p className="career-form-note">
                Setelah mengirim, buka email verifikasi untuk mengonfirmasi pengajuan. Tim HR tetap
                memeriksa identitas dan CV; pengiriman tidak menjamin kelanjutan seleksi.
            </p>
        </form>
    );
}
function Directory(p: Props) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const update = (d: Record<string, string>) =>
        router.get(
            '/karier',
            { ...p.filters, q, ...d, page: 1 },
            { preserveState: false, preserveScroll: true },
        );
    return (
        <>
            <div className="career-intro">
                <p className="career-kicker">KARIER</p>
                <h1>
                    Temukan peran
                    <br />
                    <span>berikutnya.</span>
                </h1>
                <p>
                    Lihat kebutuhan posisi yang sedang dibuka. Kenali pekerjaannya, temukan
                    kecocokannya, lalu kirim lamaran Anda.
                </p>
            </div>
            <div className="career-filters">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        update({ q });
                    }}
                >
                    <Search size={17} aria-hidden="true" />
                    <input
                        aria-label="Cari lowongan"
                        placeholder="Cari posisi atau lokasi…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button>Cari</button>
                </form>
                <select
                    aria-label="Departemen"
                    value={p.filters?.department_id ?? ''}
                    onChange={(e) => update({ department_id: e.target.value })}
                >
                    <option value="">Semua departemen</option>
                    {p.departments?.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Jenis kerja"
                    value={p.filters?.type ?? ''}
                    onChange={(e) => update({ type: e.target.value })}
                >
                    <option value="">Semua jenis kerja</option>
                    {p.types?.map((t) => (
                        <option key={t.value} value={t.value}>
                            {t.label}
                        </option>
                    ))}
                </select>
            </div>
            <p className="career-count">{p.vacancies?.total ?? 0} lowongan tersedia</p>
            <CareerJobs jobs={p.vacancies?.data ?? []} />
            {!p.vacancies?.data.length && (
                <div className="career-empty">
                    <h2>Belum ada posisi yang cocok.</h2>
                    <p>Sesuaikan pencarian atau kunjungi kembali untuk melihat lowongan terbaru.</p>
                    <Link href="/karier">
                        Lihat semua lowongan <ArrowRight size={16} />
                    </Link>
                </div>
            )}
            <div className="career-pagination">
                <span>
                    {p.vacancies?.from ?? 0}–{p.vacancies?.to ?? 0} dari {p.vacancies?.total ?? 0}{' '}
                    lowongan
                </span>
                <nav aria-label="Halaman lowongan">
                    {p.vacancies?.links.map((l, i) =>
                        l.url ? (
                            <Link key={i} href={l.url} aria-current={l.active ? 'page' : undefined}>
                                {i === 0
                                    ? 'Sebelumnya'
                                    : i === p.vacancies!.links.length - 1
                                      ? 'Berikutnya'
                                      : l.label}
                            </Link>
                        ) : (
                            <span key={i}>
                                {i === 0
                                    ? 'Sebelumnya'
                                    : i === p.vacancies!.links.length - 1
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
export default function Careers(p: Props) {
    const job = p.vacancy;
    return (
        <div className="taste-page northstar-page career-page">
            <Head
                title={
                    job
                        ? `${p.mode === 'apply' ? 'Lamar · ' : ''}${job.job_title}`
                        : ((
                              {
                                  thanks: 'Pengajuan lamaran',
                                  verify: 'Konfirmasi email',
                                  verified: 'Email terverifikasi',
                                  resend: 'Kirim ulang verifikasi',
                              } as Record<string, string>
                          )[p.mode] ?? 'Lowongan kerja')
                }
            >
                <meta
                    name="description"
                    content={
                        job
                            ? `${job.job_title} · ${job.location} · ${job.type_label}`
                            : 'Temukan lowongan yang sedang dibuka dan kirim lamaran Anda.'
                    }
                />
                {p.mode !== 'index' && p.mode !== 'show' && (
                    <meta name="robots" content="noindex, nofollow" />
                )}
            </Head>
            <a href="#career-main" className="taste-skip">
                Lewati ke konten
            </a>
            <PublicHeader careers />
            <main id="career-main" className="taste-wrap career-main">
                {p.mode === 'index' && <Directory key={JSON.stringify(p.filters)} {...p} />}{' '}
                {(p.mode === 'verify' || p.mode === 'verified') && (
                    <section className="career-thanks">
                        <Check size={28} />
                        <h1>
                            {p.mode === 'verified'
                                ? 'Email telah terverifikasi.'
                                : 'Konfirmasikan lamaran Anda.'}
                        </h1>
                        <p>
                            {p.mode === 'verified'
                                ? 'Pengajuan terverifikasi dapat diproses oleh tim HR. Bukti penerimaan akan dikirim melalui email. Ini bukan pernyataan lolos seleksi.'
                                : 'Tekan tombol berikut hanya jika Anda sendiri mengirim lamaran ini. Konfirmasi email tidak membuktikan identitas atau kebenaran isi CV.'}
                        </p>
                        {p.mode === 'verify' && p.confirmationUrl && (
                            <Confirmation url={p.confirmationUrl} />
                        )}
                        <Link href="/karier" className="career-back">
                            Kembali ke lowongan
                        </Link>
                    </section>
                )}
                {p.mode === 'resend' && <Resend />}
                {p.mode === 'thanks' && (
                    <section className="career-thanks">
                        <Check size={28} />
                        <h1>
                            Terima kasih
                            <br />
                            atas pengajuan Anda.
                        </h1>
                        <p>
                            Untuk pengajuan baru yang memenuhi ketentuan, email verifikasi akan
                            dikirim ke alamat yang Anda isi. Periksa inbox dan spam, lalu
                            konfirmasikan dalam 24 jam agar tim HR dapat melanjutkan proses.
                        </p>
                        <p>
                            Pengiriman ulang posisi yang sama tidak menambah lamaran atau mengganti
                            CV. Jika Anda tidak menerima email, gunakan kirim ulang. Demi privasi,
                            halaman ini tidak menunjukkan apakah alamat email sudah terdaftar.
                        </p>
                        <Link href="/karier/verifikasi/kirim-ulang" className="career-back">
                            Kirim ulang email verifikasi
                        </Link>
                        <Link className="taste-button" href="/karier">
                            Kembali ke lowongan <ArrowRight size={17} />
                        </Link>
                    </section>
                )}
                {job && (
                    <>
                        <Link
                            className="career-back"
                            href={p.mode === 'apply' ? `/karier/${job.id}` : '/karier'}
                        >
                            <ArrowLeft size={15} />{' '}
                            {p.mode === 'apply' ? 'Kembali ke detail' : 'Semua lowongan'}
                        </Link>
                        <div className="career-job-heading">
                            <p className="career-kicker">{job.department}</p>
                            <h1>{job.job_title}</h1>
                            <p>
                                {job.location} <span>·</span> {job.type_label}
                            </p>
                        </div>
                        <div className="career-detail-grid">
                            <div>
                                {p.mode === 'apply' ? (
                                    <Apply job={job} />
                                ) : (
                                    <div className="career-copy">
                                        <section>
                                            <h2>Deskripsi pekerjaan</h2>
                                            <p>{job.description}</p>
                                        </section>
                                        <section>
                                            <h2>Yang kami cari</h2>
                                            <p>{job.requirements}</p>
                                        </section>
                                    </div>
                                )}
                            </div>
                            <aside className="career-summary">
                                <h2>Ringkasan posisi</h2>
                                <dl>
                                    <dt>Departemen</dt>
                                    <dd>{job.department}</dd>
                                    <dt>Lokasi</dt>
                                    <dd>{job.location}</dd>
                                    <dt>Jenis kerja</dt>
                                    <dd>{job.type_label}</dd>
                                    <dt>Batas lamaran</dt>
                                    <dd>{careerDate(job.closing_date)}</dd>
                                </dl>
                                {p.mode === 'show' && (
                                    <Link href={`/karier/${job.id}/lamar`} className="taste-button">
                                        Lamar posisi ini <ArrowUpRight size={17} />
                                    </Link>
                                )}
                                <p>
                                    Kirim lamaran tanpa membuat akun. CV hanya diakses tim HR yang
                                    berwenang.
                                </p>
                            </aside>
                        </div>
                    </>
                )}
            </main>
            <footer className="taste-wrap career-footer">
                <Link href="/" className="taste-wordmark">
                    HRIS<span>Lite</span>
                </Link>
                <span>Setiap orang, sebuah perjalanan.</span>
            </footer>
        </div>
    );
}
