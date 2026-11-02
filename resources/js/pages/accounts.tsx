import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ArrowRight, Copy, KeyRound } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import { RecordField as Field, RecordPagination } from '../components/record-fields';
import { StatusBadge } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import { applicationDate as date } from '../components/application-format';
import '../css/employees.css';
import '../css/applications.css';
import '../css/trainings.css';
import '../css/accounts.css';
type Person = {
    id: number;
    full_name: string;
    employee_number: string;
    version?: number;
    manager_employee_id?: number | null;
    manager_name?: string | null;
};
type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    employee_id: number | null;
    employee_name: string | null;
    employee_number: string | null;
    version: number;
};
type Invitation = {
    id: number;
    email: string;
    role: string;
    employee_name: string | null;
    version: number;
    status: string;
    expires_at: string;
};
type Page<T> = {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
type Props = {
    auth: { user: HrUser & { id: number } };
    flash: { success: string | null };
    errors?: Record<string, string>;
    roles?: Record<string, string>;
    mode: 'index' | 'show' | 'reporting' | 'reporting-edit';
    accounts?: Page<Account>;
    account?: Account;
    employees?: Person[];
    employeesPage?: Page<Person>;
    employee?: Person;
    managers?: Person[];
    filters?: { q?: string; role?: string; employee_q?: string; manager_q?: string };
    invitations?: Page<Invitation>;
    accessLink?: string | null;
    events?: {
        id: number;
        action: string;
        actor: string;
        reason: string | null;
        created_at: string;
    }[];
};
const roles: Record<string, string> = {
    admin: 'Admin',
    hr_staff: 'Staf HR',
    viewer: 'Pembaca',
    employee: 'Pegawai',
    supervisor: 'Atasan',
};
const eventLabels: Record<string, string> = {
    account_updated: 'Akses akun diperbarui',
    reset_requested: 'Pemulihan diminta',
    password_reset: 'Kata sandi diperbarui',
    account_activated: 'Akun diaktifkan',
};
function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {Object.entries(errors).map(([k, v]) => (
                <p key={k} className="ep-error" role="alert">
                    {v}
                </p>
            ))}
        </>
    );
}
function AccessLink({ url }: { url: string }) {
    const [message, setMessage] = useState('');
    return (
        <section className="ep-card ac-link">
            <h2>Tautan akses</h2>
            <p>
                Salurkan secara privat kepada pemilik akun. Tautan tidak dikirim otomatis dan hanya
                ditampilkan setelah dibuat.
            </p>
            <input aria-label="Tautan akses" readOnly value={url} />
            <button
                className="ep-secondary"
                onClick={async () => {
                    try {
                        if (!navigator.clipboard) throw new Error();
                        await navigator.clipboard.writeText(url);
                        setMessage('Tautan disalin.');
                    } catch {
                        setMessage('Salin tautan dari kolom di atas.');
                    }
                }}
            >
                <Copy size={14} />
                Salin tautan
            </button>
            {message && <small role="status">{message}</small>}
        </section>
    );
}
function EmployeeSearch({ p }: { p: Props }) {
    const [q, setQ] = useState(p.filters?.employee_q ?? '');
    return (
        <form
            className="tr-search"
            onSubmit={(e) => {
                e.preventDefault();
                router.get(
                    p.account ? `/accounts/${p.account.id}` : '/accounts',
                    { employee_q: q },
                    { preserveState: true, preserveScroll: true },
                );
            }}
        >
            <input
                aria-label="Cari pegawai untuk akun"
                placeholder="Cari nama atau nomor pegawai"
                value={q}
                onChange={(e) => setQ(e.target.value)}
            />
            <button className="ep-secondary">Cari pegawai</button>
        </form>
    );
}
function AccountForm({ p }: { p: Props }) {
    const a = p.account;
    const [selected, setSelected] = useState<Person | null>(null);
    const f = useForm({
        email: '',
        role: a?.role ?? 'employee',
        employee_id: a?.employee_id?.toString() ?? '',
        is_active: a?.is_active ?? true,
        version: a?.version ?? 1,
        reason: '',
        current_password: '',
    });
    const options = [...(p.employees ?? [])];
    if (selected && !options.some((e) => e.id === selected.id)) options.push(selected);
    return (
        <form
            aria-label={a ? 'Pengaturan akses akun' : 'Undangan akun'}
            className="ep-card ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                const o = { onFinish: () => f.reset('current_password') };
                if (a) f.put(`/accounts/${a.id}`, o);
                else f.post('/accounts/invitations', { ...o, onSuccess: () => f.reset() });
            }}
        >
            <h2>{a ? 'Pengaturan akses' : 'Undang anggota'}</h2>
            <Errors errors={f.errors} />
            {!a && (
                <Field label="Email akun">
                    <input
                        type="email"
                        required
                        maxLength={255}
                        value={f.data.email}
                        onChange={(e) => f.setData('email', e.target.value)}
                    />
                </Field>
            )}
            <div className="tr-form-grid">
                <Field label="Role">
                    <select
                        value={f.data.role}
                        disabled={a?.id === p.auth.user.id}
                        onChange={(e) => f.setData('role', e.target.value)}
                    >
                        {Object.entries(p.roles ?? roles).map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Hubungkan pegawai">
                    <select
                        required={['employee', 'supervisor'].includes(f.data.role)}
                        value={f.data.employee_id}
                        onChange={(e) => {
                            f.setData('employee_id', e.target.value);
                            setSelected(
                                options.find((p) => p.id.toString() === e.target.value) ?? null,
                            );
                        }}
                    >
                        <option value="">Tidak dihubungkan</option>
                        {options.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.full_name} · {e.employee_number}
                            </option>
                        ))}
                    </select>
                </Field>
            </div>
            <p className="tr-caption">
                Pegawai dan Atasan wajib terhubung ke pegawai aktif. Satu pegawai hanya memiliki
                satu akun. Gunakan pencarian di atas bila pegawai belum muncul.
            </p>
            {a && (
                <>
                    <label className="ac-confirm">
                        <input
                            type="checkbox"
                            checked={f.data.is_active}
                            disabled={a.id === p.auth.user.id}
                            onChange={(e) => f.setData('is_active', e.target.checked)}
                        />
                        Akun aktif
                    </label>
                    <Field label="Alasan perubahan">
                        <textarea
                            required
                            rows={3}
                            minLength={10}
                            maxLength={1000}
                            value={f.data.reason}
                            onChange={(e) => f.setData('reason', e.target.value)}
                        />
                    </Field>
                </>
            )}
            <Field label="Kata sandi Admin">
                <input
                    type="password"
                    required
                    autoComplete="current-password"
                    value={f.data.current_password}
                    onChange={(e) => f.setData('current_password', e.target.value)}
                />
            </Field>
            <button className="ep-primary" disabled={f.processing}>
                {f.processing ? 'Memproses…' : a ? 'Simpan akses akun' : 'Buat undangan'}
            </button>
        </form>
    );
}
function ResetForm({ a }: { a: Account }) {
    const f = useForm({ version: a.version, reason: '', current_password: '' });
    return (
        <form
            aria-label="Pemulihan kata sandi"
            className="ep-card ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/accounts/${a.id}/reset-link`, {
                    onFinish: () => f.reset('current_password'),
                });
            }}
        >
            <h2>Pemulihan kata sandi</h2>
            <p className="tr-copy">
                Buat tautan sekali pakai yang berlaku 60 menit. Pemilik akun menentukan kata sandi
                barunya sendiri.
            </p>
            <Errors errors={f.errors} />
            <Field label="Alasan pemulihan">
                <textarea
                    required
                    rows={3}
                    minLength={10}
                    maxLength={1000}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <Field label="Kata sandi Admin">
                <input
                    type="password"
                    required
                    autoComplete="current-password"
                    value={f.data.current_password}
                    onChange={(e) => f.setData('current_password', e.target.value)}
                />
            </Field>
            <button className="ep-secondary" disabled={f.processing || !a.is_active}>
                <KeyRound size={14} />
                Buat tautan pemulihan
            </button>
        </form>
    );
}
function RevokeForm({ inv, onSuccess }: { inv: Invitation; onSuccess: () => void }) {
    const f = useForm({ version: inv.version, reason: '', current_password: '' });
    return (
        <form
            className="ep-card ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/accounts/invitations/${inv.id}/revoke`, {
                    onFinish: () => f.reset('current_password'),
                    onSuccess,
                });
            }}
        >
            <h2>Cabut undangan {inv.email}</h2>
            <Errors errors={f.errors} />
            <Field label="Alasan pencabutan">
                <textarea
                    required
                    minLength={10}
                    rows={3}
                    maxLength={1000}
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>
            <Field label="Kata sandi Admin">
                <input
                    type="password"
                    required
                    autoComplete="current-password"
                    value={f.data.current_password}
                    onChange={(e) => f.setData('current_password', e.target.value)}
                />
            </Field>
            <button className="ep-secondary" disabled={f.processing}>
                Cabut undangan
            </button>
        </form>
    );
}
function ReportingForm({ p }: { p: Props }) {
    const e = p.employee!;
    const [selected, setSelected] = useState<Person | null>(null);
    const [q, setQ] = useState(p.filters?.manager_q ?? '');
    const f = useForm({
        employee_id: e.id,
        manager_employee_id: e.manager_employee_id?.toString() ?? '',
        version: e.version!,
        reason: '',
        current_password: '',
    });
    const options = [...(p.managers ?? [])];
    if (selected && !options.some((manager) => manager.id === selected.id)) options.push(selected);
    return (
        <>
            <form
                className="tr-search"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        `/accounts/reporting/${e.id}`,
                        { manager_q: q },
                        { preserveState: true, preserveScroll: true },
                    );
                }}
            >
                <input
                    aria-label="Cari atasan"
                    placeholder="Cari nama atau nomor atasan"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                />
                <button className="ep-secondary">Cari atasan</button>
            </form>
            <form
                className="ep-card ap-stacked tr-schedule"
                onSubmit={(event) => {
                    event.preventDefault();
                    f.post('/accounts/reporting', { onFinish: () => f.reset('current_password') });
                }}
            >
                <h2>
                    {e.full_name} · {e.employee_number}
                </h2>
                <Errors errors={f.errors} />
                <Field label="Atasan langsung">
                    <select
                        value={f.data.manager_employee_id}
                        onChange={(event) => {
                            f.setData('manager_employee_id', event.target.value);
                            setSelected(
                                options.find(
                                    (manager) => manager.id.toString() === event.target.value,
                                ) ?? null,
                            );
                        }}
                    >
                        <option value="">Tanpa atasan langsung</option>
                        {options.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.full_name} · {m.employee_number}
                            </option>
                        ))}
                    </select>
                </Field>
                <p className="tr-caption">
                    Atasan harus memiliki akun Atasan aktif. Anggota tim yang terlihat hanya pegawai
                    yang melapor langsung.
                </p>
                <Field label="Alasan penetapan">
                    <textarea
                        required
                        minLength={10}
                        maxLength={1000}
                        rows={3}
                        value={f.data.reason}
                        onChange={(event) => f.setData('reason', event.target.value)}
                    />
                </Field>
                <Field label="Kata sandi Admin">
                    <input
                        type="password"
                        required
                        autoComplete="current-password"
                        value={f.data.current_password}
                        onChange={(event) => f.setData('current_password', event.target.value)}
                    />
                </Field>
                <button className="ep-primary" disabled={f.processing}>
                    Simpan atasan
                </button>
            </form>
        </>
    );
}
export default function Accounts(p: Props) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const [role, setRole] = useState(p.filters?.role ?? '');
    const [revoke, setRevoke] = useState<number | null>(null);
    const inv = p.invitations?.data.find((i) => i.id === revoke);
    const a = p.account;
    const reporting = p.mode.startsWith('reporting');
    return (
        <HrShell title="Pengelolaan akun" active="accounts" user={p.auth.user}>
            <main id="hr-main" className="hr-main ep-main ac-page">
                <nav className="ep-tabs" aria-label="Pengaturan akun">
                    <Link href="/accounts" aria-current={!reporting ? 'page' : undefined}>
                        Akun & undangan
                    </Link>
                    <Link href="/accounts/reporting" aria-current={reporting ? 'page' : undefined}>
                        Atasan langsung
                    </Link>
                </nav>
                {(p.mode === 'show' || p.mode === 'reporting-edit') && (
                    <Link
                        className="ep-back"
                        href={reporting ? '/accounts/reporting' : '/accounts'}
                    >
                        <ArrowLeft size={14} />
                        Kembali ke daftar
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>
                            {p.mode === 'show'
                                ? a!.name
                                : reporting
                                  ? 'Atasan langsung'
                                  : 'Akun & undangan'}
                        </h1>
                        <p>
                            {a
                                ? a.email
                                : reporting
                                  ? 'Tetapkan hubungan pegawai dan atasan untuk akses tim.'
                                  : 'Atur akses anggota dan hubungkan akun dengan data pegawai.'}
                        </p>
                    </div>
                </div>
                {p.flash?.success && (
                    <p className="ep-alert" role="status">
                        {p.flash.success}
                    </p>
                )}
                <Errors errors={p.errors ?? {}} />
                {p.accessLink && <AccessLink key={p.accessLink} url={p.accessLink} />}
                {p.mode === 'index' && (
                    <>
                        <form
                            className="tr-list-filters"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get('/accounts', { q, role });
                            }}
                        >
                            <input
                                aria-label="Cari akun"
                                placeholder="Nama atau email akun"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <select
                                aria-label="Filter role"
                                value={role}
                                onChange={(e) => {
                                    setRole(e.target.value);
                                    router.get('/accounts', { q, role: e.target.value });
                                }}
                            >
                                <option value="">Semua role</option>
                                {Object.entries(p.roles ?? roles).map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                            <button className="ep-secondary">Cari</button>
                        </form>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>{p.accounts?.total ?? 0} akun</h2>
                            </div>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Anggota</th>
                                            <th>Role</th>
                                            <th>Pegawai</th>
                                            <th>Status</th>
                                            <th>
                                                <span className="sr-only">Pengaturan</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.accounts?.data.map((a) => (
                                            <tr key={a.id}>
                                                <td>
                                                    <strong>{a.name}</strong>
                                                    <small className="tr-sub">{a.email}</small>
                                                </td>
                                                <td>{roles[a.role] ?? a.role}</td>
                                                <td>
                                                    {a.employee_name ?? '—'}
                                                    <small className="tr-sub">
                                                        {a.employee_number}
                                                    </small>
                                                </td>
                                                <td>
                                                    <StatusBadge
                                                        variant={
                                                            a.is_active ? 'success' : 'expired'
                                                        }
                                                    >
                                                        {a.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </StatusBadge>
                                                </td>
                                                <td>
                                                    <Link
                                                        aria-label={`Atur akun ${a.name}`}
                                                        href={`/accounts/${a.id}`}
                                                    >
                                                        <ArrowRight size={16} />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.accounts?.data.length && (
                                <p className="hr-empty">Tidak ada akun yang cocok.</p>
                            )}
                        </section>
                        {p.accounts && (
                            <RecordPagination total={p.accounts.total} links={p.accounts.links} />
                        )}
                        <div className="ac-invite">
                            <EmployeeSearch p={p} />
                            <AccountForm p={p} />
                        </div>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>Undangan akun</h2>
                            </div>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Email / pegawai</th>
                                            <th>Role</th>
                                            <th>Berlaku sampai</th>
                                            <th>Status</th>
                                            <th>Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.invitations?.data.map((i) => (
                                            <tr key={i.id}>
                                                <td>
                                                    {i.email}
                                                    <small className="tr-sub">
                                                        {i.employee_name}
                                                    </small>
                                                </td>
                                                <td>{roles[i.role]}</td>
                                                <td>{date(i.expires_at, true)} WIB</td>
                                                <td>
                                                    {i.status === 'pending'
                                                        ? 'Menunggu aktivasi'
                                                        : i.status === 'used'
                                                          ? 'Sudah digunakan'
                                                          : 'Tidak berlaku'}
                                                </td>
                                                <td>
                                                    {i.status === 'pending' && (
                                                        <button
                                                            className="hr-text-button"
                                                            onClick={() => setRevoke(i.id)}
                                                        >
                                                            Cabut
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.invitations?.data.length && (
                                <p className="hr-empty">Belum ada undangan.</p>
                            )}
                        </section>
                        {p.invitations && (
                            <RecordPagination
                                total={p.invitations.total}
                                links={p.invitations.links}
                            />
                        )}{' '}
                        {inv && (
                            <RevokeForm
                                key={`${inv.id}-${inv.version}`}
                                inv={inv}
                                onSuccess={() => setRevoke(null)}
                            />
                        )}
                    </>
                )}
                {p.mode === 'show' && a && (
                    <>
                        <EmployeeSearch p={p} />
                        <div className="tr-layout">
                            <div>
                                <AccountForm key={`${a.id}-${a.version}`} p={p} />
                                <ResetForm key={`reset-${a.id}-${a.version}`} a={a} />
                            </div>
                            <aside>
                                <section className="ep-card">
                                    <h2>Riwayat akun</h2>
                                    <ProcessTimeline
                                        items={(p.events ?? []).map((e) => ({
                                            label: `${eventLabels[e.action] ?? e.action} · #${e.id}`,
                                            time: `${date(e.created_at, true)} WIB`,
                                            state: 'done' as const,
                                            note: [e.actor, e.reason].filter(Boolean).join(' · '),
                                        }))}
                                    />
                                    {!p.events?.length && (
                                        <p className="tr-copy">
                                            Belum ada perubahan akun tercatat.
                                        </p>
                                    )}
                                </section>
                            </aside>
                        </div>
                    </>
                )}
                {p.mode === 'reporting' && (
                    <>
                        <form
                            className="tr-search"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get('/accounts/reporting', { q });
                            }}
                        >
                            <input
                                aria-label="Cari pegawai dalam struktur"
                                placeholder="Nama atau nomor pegawai"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <button className="ep-secondary">Cari</button>
                        </form>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>{p.employeesPage?.total ?? 0} pegawai aktif</h2>
                            </div>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Pegawai</th>
                                            <th>Atasan langsung</th>
                                            <th>Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.employeesPage?.data.map((e) => (
                                            <tr key={e.id}>
                                                <td>
                                                    <strong>{e.full_name}</strong>
                                                    <small className="tr-sub">
                                                        {e.employee_number}
                                                    </small>
                                                </td>
                                                <td>{e.manager_name ?? 'Belum ditetapkan'}</td>
                                                <td>
                                                    <Link href={`/accounts/reporting/${e.id}`}>
                                                        Atur atasan
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.employeesPage?.data.length && (
                                <p className="hr-empty">Belum ada pegawai yang cocok.</p>
                            )}
                        </section>
                        {p.employeesPage && (
                            <RecordPagination
                                total={p.employeesPage.total}
                                links={p.employeesPage.links}
                            />
                        )}
                    </>
                )}
                {p.mode === 'reporting-edit' && (
                    <ReportingForm key={`${p.employee!.id}-${p.employee!.version}`} p={p} />
                )}
            </main>
        </HrShell>
    );
}
