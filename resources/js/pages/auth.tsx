import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Eye, EyeOff, KeyRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import '../css/auth.css';

type Props = {
    mode: 'login' | 'register' | 'confirm';
    invitation: { email: string; token: string } | null;
    invalidInvitation?: boolean;
};

export default function Auth({ mode, invitation, invalidInvitation = false }: Props) {
    const signup = mode === 'register';
    const confirm = mode === 'confirm';
    const activeSignup = signup && invitation !== null;
    const [visible, setVisible] = useState(false);
    const form = useForm({
        email: '',
        password: '',
        password_confirmation: '',
        name: '',
        remember: false,
        token: invitation?.token ?? '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(signup ? '/register' : confirm ? '/user/confirm-password' : '/login', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
    const title = signup
        ? 'Buat akun Anda.'
        : confirm
          ? 'Konfirmasi akses.'
          : 'Masuk ke ruang kerja.';

    return (
        <div className="auth-page">
            <Head title={signup ? 'Aktivasi akun' : confirm ? 'Konfirmasi kata sandi' : 'Masuk'}>
                <meta name="robots" content="noindex, nofollow" />
                <meta name="referrer" content="no-referrer" />
            </Head>
            <header className="auth-header">
                <Link href="/" className="auth-brand" aria-label="HRIS Lite, beranda">
                    HRIS<span>Lite</span>
                    <ArrowRight size={19} aria-hidden="true" />
                </Link>
                <Link href="/" className="auth-back">
                    <ArrowLeft size={15} aria-hidden="true" /> Beranda
                </Link>
            </header>
            <main className="auth-layout">
                <aside className="auth-story" aria-label="Tentang ruang kerja">
                    <p className="auth-eyebrow">RUANG KERJA HUMAN RESOURCES</p>
                    <h2>
                        Orangnya terhubung.
                        <br />
                        <span>Prosesnya tertata.</span>
                    </h2>
                    <p className="auth-intro">
                        Satu tempat untuk mengikuti perjalanan tim, dari berkas pertama hingga
                        langkah berikutnya.
                    </p>
                    <div className="auth-record">
                        <div className="auth-record-head">
                            <span>PERJALANAN KANDIDAT</span>
                            <span>Contoh alur</span>
                        </div>
                        {['Berkas diterima', 'Wawancara dijadwalkan', 'Bergabung dengan tim'].map(
                            (step, index) => (
                                <div className="auth-step" key={step}>
                                    <span aria-hidden="true">
                                        {index < 2 ? <Check size={13} /> : '03'}
                                    </span>
                                    <div>
                                        <strong>{step}</strong>
                                        <p>
                                            {
                                                [
                                                    'Informasi tersimpan dalam satu catatan.',
                                                    'Tim HR menyiapkan langkah berikutnya.',
                                                    'Awal dari perjalanan yang baru.',
                                                ][index]
                                            }
                                        </p>
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                    <p className="auth-story-note">Pegawai · Rekrutmen · Administrasi HR</p>
                </aside>
                <section className="auth-panel" aria-labelledby="auth-title">
                    <div className="auth-tabs" aria-label="Akses akun">
                        <Link href="/login" aria-current={!signup ? 'page' : undefined}>
                            Masuk
                        </Link>
                        <Link href="/register" aria-current={signup ? 'page' : undefined}>
                            Buat akun
                        </Link>
                    </div>
                    <h1 id="auth-title">{title}</h1>
                    <p className="auth-subtitle">
                        {signup
                            ? 'Aktifkan akses melalui undangan dari pengelola.'
                            : confirm
                              ? 'Masukkan kembali kata sandi untuk melanjutkan.'
                              : 'Gunakan akun yang diberikan oleh pengelola tim.'}
                    </p>
                    {signup && !activeSignup ? (
                        <div className="auth-invite-info">
                            <KeyRound size={25} strokeWidth={1.5} aria-hidden="true" />
                            <h2>
                                {invalidInvitation
                                    ? 'Undangan tidak tersedia.'
                                    : 'Punya undangan dari tim?'}
                            </h2>
                            <p>
                                {invalidInvitation
                                    ? 'Tautan sudah digunakan, kedaluwarsa, atau tidak valid. Hubungi pengelola untuk mendapatkan undangan baru.'
                                    : 'Buka tautan undangan yang diberikan pengelola untuk mengisi nama dan membuat kata sandi. Pendaftaran akun dibatasi untuk anggota tim.'}
                            </p>
                            <Link href="/login">
                                Sudah punya akun? Masuk <ArrowRight size={15} aria-hidden="true" />
                            </Link>
                        </div>
                    ) : (
                        <form onSubmit={submit} className="auth-form">
                            {form.errors.token && (
                                <p className="auth-error auth-error-box" role="alert">
                                    {form.errors.token}
                                </p>
                            )}
                            {activeSignup && (
                                <div className="auth-field">
                                    <label htmlFor="name">Nama lengkap</label>
                                    <input
                                        id="name"
                                        name="name"
                                        autoComplete="name"
                                        autoFocus
                                        required
                                        maxLength={120}
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        aria-invalid={!!form.errors.name}
                                        aria-describedby={
                                            form.errors.name ? 'name-error' : undefined
                                        }
                                    />
                                    {form.errors.name && (
                                        <p id="name-error" className="auth-error" role="alert">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>
                            )}
                            {!confirm && (
                                <div className="auth-field">
                                    <label htmlFor="email">Email kerja</label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        autoComplete="username"
                                        autoFocus={!signup}
                                        required
                                        maxLength={255}
                                        readOnly={activeSignup}
                                        value={activeSignup ? invitation.email : form.data.email}
                                        placeholder="nama@perusahaan.com"
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        aria-invalid={!!form.errors.email}
                                        aria-describedby={
                                            form.errors.email ? 'email-error' : undefined
                                        }
                                    />
                                    {form.errors.email && (
                                        <p id="email-error" className="auth-error" role="alert">
                                            {form.errors.email}
                                        </p>
                                    )}
                                </div>
                            )}
                            <div className="auth-field">
                                <label htmlFor="password">Kata sandi</label>
                                <div className="auth-password">
                                    <input
                                        id="password"
                                        name="password"
                                        type={visible ? 'text' : 'password'}
                                        autoComplete={signup ? 'new-password' : 'current-password'}
                                        required
                                        minLength={signup ? 12 : undefined}
                                        maxLength={72}
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        aria-invalid={!!form.errors.password}
                                        aria-describedby={
                                            signup
                                                ? 'password-hint password-error'
                                                : form.errors.password
                                                  ? 'password-error'
                                                  : undefined
                                        }
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setVisible(!visible)}
                                        aria-label={
                                            visible
                                                ? 'Sembunyikan kata sandi'
                                                : 'Tampilkan kata sandi'
                                        }
                                        aria-pressed={visible}
                                    >
                                        {visible ? <EyeOff size={18} /> : <Eye size={18} />}
                                    </button>
                                </div>
                                {signup && (
                                    <p id="password-hint" className="auth-hint">
                                        Minimal 12 karakter, dengan huruf besar, huruf kecil, dan
                                        angka.
                                    </p>
                                )}
                                <p
                                    id="password-error"
                                    className="auth-error"
                                    role={form.errors.password ? 'alert' : undefined}
                                >
                                    {form.errors.password}
                                </p>
                            </div>
                            {activeSignup && (
                                <div className="auth-field">
                                    <label htmlFor="password_confirmation">Ulangi kata sandi</label>
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type={visible ? 'text' : 'password'}
                                        autoComplete="new-password"
                                        required
                                        maxLength={72}
                                        value={form.data.password_confirmation}
                                        onChange={(e) =>
                                            form.setData('password_confirmation', e.target.value)
                                        }
                                    />
                                </div>
                            )}
                            {!signup && !confirm && (
                                <label className="auth-remember">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remember}
                                        onChange={(e) => form.setData('remember', e.target.checked)}
                                    />{' '}
                                    Tetap masuk di perangkat ini
                                </label>
                            )}
                            <button
                                className="auth-submit"
                                type="submit"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Memproses…'
                                    : signup
                                      ? 'Aktifkan akun'
                                      : confirm
                                        ? 'Konfirmasi'
                                        : 'Masuk'}
                                <ArrowRight size={17} aria-hidden="true" />
                            </button>
                            <p className="auth-help">
                                {signup
                                    ? 'Akun terhubung ke email dan akses yang ditetapkan pengelola.'
                                    : 'Kesulitan masuk? Hubungi pengelola akun tim Anda.'}
                            </p>
                        </form>
                    )}
                    <div className="auth-footnote">
                        <span className="auth-dot" /> Akses khusus anggota tim
                    </div>
                </section>
            </main>
            <footer className="auth-footer">
                <span>HRIS Lite</span>
                <span>Informasi terhubung. Tim bertumbuh.</span>
            </footer>
        </div>
    );
}
