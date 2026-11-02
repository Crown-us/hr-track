import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import HrShell, { type HrUser } from '../components/hr-shell';
import '../css/employees.css';
import '../css/applications.css';
import '../css/directories.css';

interface PositionRow {
    id: number;
    name: string;
    department: string;
    department_id: number;
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface PositionsProps {
    positions: {
        data: PositionRow[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        flash?: { success?: string };
    };
    mode: string;
    position: PositionRow | null;
    departments: DepartmentOption[];
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

function PositionForm({
    position,
    departments,
}: {
    position: PositionRow | null;
    departments: DepartmentOption[];
}) {
    const f = useForm<{ department_id: string; name: string }>({
        department_id: String(position?.department_id ?? ''),
        name: position?.name ?? '',
    });

    return (
        <form
            className="ep-card ep-form ap-stacked"
            onSubmit={(e) => {
                e.preventDefault();
                if (position) {
                    f.put('/positions/' + position.id);
                } else {
                    f.post('/positions');
                }
            }}
        >
            <div className="ep-section-heading">
                <h2>{position ? 'Ubah Jabatan' : 'Tambah Jabatan'}</h2>
            </div>
            <Field label="Departemen" error={f.errors.department_id}>
                <select
                    required
                    value={f.data.department_id}
                    onChange={(e) => f.setData('department_id', e.target.value)}
                >
                    <option value="">Pilih departemen</option>
                    {departments.map((d) => (
                        <option key={d.id} value={String(d.id)}>
                            {d.name}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Nama jabatan" error={f.errors.name}>
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
                    onClick={() => router.get('/positions')}
                >
                    Batal
                </button>
            </div>
        </form>
    );
}

export default function Positions({
    positions,
    mode,
    position,
    departments,
    canCreate,
}: PositionsProps) {
    const { auth, flash } = usePage<{ auth: { user: HrUser }; flash: { success?: string } }>()
        .props;
    const rows = positions?.data ?? [];

    if (mode === 'create' || mode === 'edit') {
        return (
            <HrShell
                title={mode === 'create' ? 'Tambah Jabatan' : 'Ubah Jabatan'}
                active="employees"
                user={auth.user}
            >
                <main id="hr-main" className="hr-main ep-main">
                    <PositionForm position={position} departments={departments} />
                </main>
            </HrShell>
        );
    }

    return (
        <HrShell title="Jabatan" active="employees" user={auth.user}>
            <main id="hr-main" className="hr-main ep-main">
                <div className="hr-page-heading">
                    <div>
                        <h1>Jabatan</h1>
                        <p>Data master jabatan untuk pegawai dan lowongan.</p>
                    </div>
                    {canCreate && (
                        <Link className="ep-primary" href="/positions/create">
                            <Plus size={16} /> Tambah Jabatan
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
                                    <th>Jabatan</th>
                                    <th>Departemen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((d) => (
                                    <tr key={d.id}>
                                        <td>{d.name}</td>
                                        <td>{d.department}</td>
                                        <td>
                                            {canCreate && (
                                                <Link href={`/positions/${d.id}/edit`}>Ubah</Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={3}>Belum ada jabatan.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ep-pagination">
                        <span>
                            {positions.from ?? 0}–{positions.to ?? 0} dari {positions.total} jabatan
                        </span>
                        <nav aria-label="Halaman jabatan">
                            {positions.links.map((l, i) =>
                                l.url ? (
                                    <Link
                                        key={i}
                                        href={l.url}
                                        aria-current={l.active ? 'page' : undefined}
                                    >
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === positions.links.length - 1
                                              ? 'Berikutnya'
                                              : l.label}
                                    </Link>
                                ) : (
                                    <span key={i}>
                                        {i === 0
                                            ? 'Sebelumnya'
                                            : i === positions.links.length - 1
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
