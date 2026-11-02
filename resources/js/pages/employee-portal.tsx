import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import PortalShell from '../components/portal-shell';
import { RecordPagination } from '../components/record-fields';
import { applicationDate as date } from '../components/application-format';
import '../css/hr.css';
import '../css/employees.css';
import '../css/trainings.css';
import '../css/employee-portal.css';
type Person = {
    id: number;
    employee_number: string;
    full_name: string;
    department: string;
    position: string;
    employment_status: string;
    join_date: string;
    work_email?: string;
    phone?: string | null;
    manager_name?: string | null;
};
type Props = {
    auth: { user: { name: string; email: string; role: string } };
    mode: 'me' | 'team' | 'member';
    employee?: Person;
    filters?: { q?: string };
    team?: {
        data: Person[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};
export default function EmployeePortal(p: Props) {
    const [q, setQ] = useState(p.filters?.q ?? '');
    const isTeam = p.mode !== 'me';
    const e = p.employee;
    const title =
        p.mode === 'me' ? 'Data saya' : p.mode === 'team' ? 'Tim saya' : 'Data anggota tim';
    return (
        <PortalShell title={title} user={p.auth.user} active={isTeam ? 'team' : 'me'}>
            <main id="hr-main" className="hr-main ep-main portal-page">
                {p.mode === 'member' && (
                    <Link className="ep-back" href="/team">
                        <ArrowLeft size={14} />
                        Kembali ke tim
                    </Link>
                )}
                <div className="hr-page-heading">
                    <div>
                        <h1>{title}</h1>
                        <p>
                            {p.mode === 'me'
                                ? 'Informasi kepegawaian Anda yang tercatat di HR.'
                                : p.mode === 'team'
                                  ? 'Anggota yang melapor langsung kepada Anda.'
                                  : 'Informasi dasar anggota tim Anda.'}
                        </p>
                    </div>
                </div>
                {e && (
                    <Link
                        className="ep-secondary"
                        style={{ marginBottom: 20 }}
                        href={p.mode === 'me' ? '/attendance' : `/attendance/employees/${e.id}`}
                    >
                        Lihat kalender kehadiran <ArrowRight size={15} />
                    </Link>
                )}
                {e && (
                    <section className="ep-card">
                        <div className="portal-person">
                            <span>
                                {e.full_name
                                    .split(' ')
                                    .map((n) => n[0])
                                    .slice(0, 2)
                                    .join('')}
                            </span>
                            <div>
                                <h2>{e.full_name}</h2>
                                <p>
                                    {e.employee_number} · {e.position}
                                </p>
                            </div>
                        </div>
                        <dl className="portal-details">
                            {[
                                ['Departemen', e.department],
                                ['Jabatan', e.position],
                                ['Status kepegawaian', e.employment_status],
                                ['Tanggal bergabung', date(e.join_date)],
                                ...(p.mode === 'me'
                                    ? [
                                          ['Email kerja', e.work_email ?? '—'],
                                          ['Telepon', e.phone ?? '—'],
                                          ['Atasan langsung', e.manager_name ?? 'Belum ditetapkan'],
                                      ]
                                    : []),
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt>{label}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ))}
                        </dl>
                        {p.mode === 'me' && (
                            <p className="tr-caption">
                                Hubungi tim HR bila ada informasi yang perlu diperbarui.
                            </p>
                        )}
                    </section>
                )}
                {p.mode === 'team' && (
                    <>
                        <form
                            className="tr-search"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get('/team', { q });
                            }}
                        >
                            <input
                                aria-label="Cari anggota tim"
                                placeholder="Nama atau nomor pegawai"
                                value={q}
                                onChange={(event) => setQ(event.target.value)}
                            />
                            <button className="ep-secondary">Cari</button>
                        </form>
                        <section className="hr-panel">
                            <div className="tr-panel-heading">
                                <h2>{p.team?.total ?? 0} anggota tim</h2>
                            </div>
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Pegawai</th>
                                            <th>Departemen</th>
                                            <th>Jabatan</th>
                                            <th>Status</th>
                                            <th>
                                                <span className="sr-only">Detail</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.team?.data.map((e) => (
                                            <tr key={e.id}>
                                                <td>
                                                    <strong>{e.full_name}</strong>
                                                    <small className="tr-sub">
                                                        {e.employee_number}
                                                    </small>
                                                </td>
                                                <td>{e.department}</td>
                                                <td>{e.position}</td>
                                                <td>{e.employment_status}</td>
                                                <td>
                                                    <Link
                                                        aria-label={`Data ${e.full_name}`}
                                                        href={`/team/${e.id}`}
                                                    >
                                                        <ArrowRight size={16} />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.team?.data.length && (
                                <p className="hr-empty">
                                    Belum ada anggota yang cocok. Hubungi Admin jika hubungan atasan
                                    belum ditetapkan.
                                </p>
                            )}
                        </section>
                        {p.team && <RecordPagination total={p.team.total} links={p.team.links} />}
                    </>
                )}
            </main>
        </PortalShell>
    );
}
