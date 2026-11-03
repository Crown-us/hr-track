import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';

interface DepartmentRow {
    id: number;
    code: string;
    name: string;
}

interface DepartmentsProps {
    departments: {
        data: DepartmentRow[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        flash?: { success?: string };
    };
    mode: string;
    department: DepartmentRow | null;
    canCreate: boolean;
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

function DepartmentForm({ department }: { department: DepartmentRow | null }) {
    const f = useForm<{ code: string; name: string }>({
        code: department?.code ?? '',
        name: department?.name ?? '',
    });

    return (
        <form
            className="ep-card ep-form ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                if (department) {
                    f.put('/departments/' + department.id);
                } else {
                    f.post('/departments');
                }
            }}
        >
            <div className="ep-section-heading">
                <h2>{department ? 'Ubah Departemen' : 'Tambah Departemen'}</h2>
            </div>
            <Field label="Kode departemen" error={f.errors.code}>
                <input
                    required
                    maxLength={50}
                    value={f.data.code}
                    onChange={(e) => f.setData('code', e.target.value)}
                />
            </Field>
            <Field label="Nama departemen" error={f.errors.name}>
                <input
                    required
                    maxLength={255}
                    value={f.data.name}
                    onChange={(e) => f.setData('name', e.target.value)}
                />
            </Field>
            <div className="ep-form-footer">
                <button className="ep-primary" disabled={f.processing}>
                    {f.processing ? 'Menyimpan…' : 'Simpan'}
                </button>
                <button
                    type="button"
                    className="hr-button"
                    onClick={() => router.get('/departments')}
                >
                    Batal
                </button>
            </div>
        </form>
    );
}

export default function Departments({
    departments,
    mode,
    department,
    canCreate,
}: DepartmentsProps) {
    const { auth, flash } = usePage<{ auth: { user: HrUser }; flash: { success?: string } }>()
        .props;
    const rows = departments?.data ?? [];

    if (mode === 'create' || mode === 'edit') {
        return (
            <HrShell
                title={mode === 'create' ? 'Tambah Departemen' : 'Ubah Departemen'}
                active="departments"
                user={auth.user}
            >
                <main id="hr-main" className="hr-main ep-main">
                    <DepartmentForm department={department} />
                </main>
            </HrShell>
        );
    }

    return (
        <HrShell title="Departemen" active="departments" user={auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                <div className="hr-page-heading">
                    <div>
                        <h1>Departemen</h1>
                        <p>Data master departemen untuk pegawai dan lowongan.</p>
                    </div>
                    {canCreate && (
                        <Link className="ep-primary" href="/departments/create">
                            <Plus size={16} /> Tambah Departemen
                        </Link>
                    )}
                </div>
                {flash?.success && (
                    <p className="ep-alert ep-success" role="status">
                        {flash?.success}
                    </p>
                )}
                <div className="hr-panel">
                    <div className="hr-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((d) => (
                                    <tr key={d.id}>
                                        <td>{d.code}</td>
                                        <td>{d.name}</td>
                                        <td>
                                            {canCreate && (
                                                <Link href={`/departments/${d.id}/edit`}>Ubah</Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={3}>Belum ada departemen.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ep-pagination">
                        <span>
                            {departments.from ?? 0}–{departments.to ?? 0} dari {departments.total}{' '}
                            departemen
                        </span>
                        <nav aria-label="Halaman departemen">
                            {departments.links.map((l, i) =>
                                l.url ? (
                                    <Link
                                        key={i}
                                        href={l.url}
                                        aria-current={l.active ? 'page' : undefined}
                                    >
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === departments.links.length - 1
                                              ? 'Berikutnya'
                                              : l.label}
                                    </Link>
                                ) : (
                                    <span key={i}>
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === departments.links.length - 1
                                              ? 'Berikutnya'
                                              : l.label}
                                    </span>
                                ),
                            )}
                        </nav>
                    </div>
                </div>
            </main>
        </HrShell>
    );
}
