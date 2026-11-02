import { Link } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';
import HrShell, { type HrUser } from './hr-shell';
import { StatusBadge } from './ui/status-badge';
import { applicationTone, applicationDate } from './application-format';
import type { EmployeeRecord } from '../types/employees';
import type { VacancyRecord } from '../types/vacancies';
import type { RecruitmentSummary } from '../types/applications';
import '../css/employees.css';

export default function HrDashboard({
    user,
    employees,
    vacancies,
    recruitment,
}: {
    user: HrUser;
    employees?: { count: number; recent: EmployeeRecord[] };
    vacancies?: { count: number; recent: VacancyRecord[] };
    recruitment: RecruitmentSummary;
}) {
    const canManage = user.role === 'admin' || user.role === 'hr_staff';
    const stages = [
        ['screening', 'Screening'],
        ['interview', 'Interview'],
        ['offering', 'Offering'],
        ['accepted', 'Diterima'],
        ['rejected', 'Ditolak'],
    ];
    return (
        <HrShell title="Ringkasan HR" active="dashboard" user={user}>
            <main id="hr-main" className="hr-main ep-main">
                <div className="hr-page-heading">
                    <div>
                        <h1>Ringkasan HR</h1>
                        <p>Gambaran pegawai dan perjalanan rekrutmen dari data yang tersimpan.</p>
                    </div>
                    {canManage && (
                        <Link className="ep-primary" href="/applications/create">
                            <Plus size={15} />
                            Tambah lamaran
                        </Link>
                    )}
                </div>
                <section className="hr-metrics" aria-label="Ringkasan database">
                    {[
                        ['Pegawai', employees?.count ?? 0, 'Dalam direktori aktif'],
                        ['Lowongan aktif', vacancies?.count ?? 0, 'Dalam periode penerimaan'],
                        [
                            'Pelamar',
                            recruitment.applicants,
                            `${recruitment.total} lamaran tercatat`,
                        ],
                        [
                            'Tahap interview',
                            recruitment.counts.interview ?? 0,
                            'Belum mencakup jadwal wawancara',
                        ],
                    ].map(([label, count, note]) => (
                        <div key={label}>
                            <p>{label}</p>
                            <strong>{count}</strong>
                            <small>{note}</small>
                        </div>
                    ))}
                </section>
                <div className="hr-overview-grid">
                    <section className="hr-panel">
                        <div className="hr-panel-title">
                            <h2>Lamaran terbaru</h2>
                            <Link href="/applications">
                                Lihat pipeline <ArrowRight size={14} />
                            </Link>
                        </div>
                        {recruitment.recent.length ? (
                            recruitment.recent.map((a) => (
                                <Link
                                    className="hr-vacancy-row"
                                    href={`/applications/${a.id}`}
                                    key={a.id}
                                >
                                    <span>
                                        <strong>{a.name}</strong>
                                        <small>
                                            {a.job_title} · {applicationDate(a.application_date)}
                                        </small>
                                    </span>
                                    <StatusBadge variant={applicationTone[a.status]}>
                                        {a.status_label}
                                    </StatusBadge>
                                    <ArrowRight size={14} />
                                </Link>
                            ))
                        ) : (
                            <p className="hr-empty">
                                Belum ada lamaran. Tambahkan pelamar melalui menu Rekrutmen →
                                Pipeline kandidat.
                            </p>
                        )}
                    </section>
                    <section className="hr-panel">
                        <div className="hr-panel-title">
                            <h2>Pipeline kandidat</h2>
                            <span>{recruitment.total} lamaran</span>
                        </div>
                        <div className="hr-pipeline-summary">
                            {stages.map(([value, label]) => (
                                <div key={value}>
                                    <span>{label}</span>
                                    <div>
                                        <span
                                            style={{
                                                width: `${recruitment.total ? Math.round(((recruitment.counts[value] ?? 0) / recruitment.total) * 100) : 0}%`,
                                            }}
                                        />
                                    </div>
                                    <strong>{recruitment.counts[value] ?? 0}</strong>
                                </div>
                            ))}
                        </div>
                        <p className="hr-footnote">
                            Jumlah lamaran per tahap, termasuk keputusan akhir.
                        </p>
                    </section>
                </div>
                <div className="hr-overview-grid hr-lower-grid">
                    <section className="hr-panel">
                        <div className="hr-panel-title">
                            <h2>Lowongan aktif</h2>
                            <Link href="/vacancies">
                                Semua lowongan <ArrowRight size={14} />
                            </Link>
                        </div>
                        {vacancies?.recent.length ? (
                            vacancies.recent.map((v) => (
                                <Link
                                    className="hr-vacancy-row"
                                    key={v.id}
                                    href={`/vacancies/${v.id}`}
                                >
                                    <span>
                                        <strong>{v.job_title}</strong>
                                        <small>{v.location}</small>
                                    </span>
                                    <span>{v.type_label}</span>
                                    <ArrowRight size={14} />
                                </Link>
                            ))
                        ) : (
                            <p className="hr-empty">Belum ada lowongan dalam periode penerimaan.</p>
                        )}
                    </section>
                    <section className="hr-panel">
                        <div className="hr-panel-title">
                            <h2>Direktori pegawai</h2>
                            <Link href="/employees">
                                Lihat semua <ArrowRight size={14} />
                            </Link>
                        </div>
                        {employees?.recent.length ? (
                            employees.recent.map((e) => (
                                <Link
                                    className="hr-vacancy-row"
                                    key={e.id}
                                    href={`/employees/${e.id}`}
                                >
                                    <span>
                                        <strong>{e.full_name}</strong>
                                        <small>
                                            {e.position} · {e.department}
                                        </small>
                                    </span>
                                    <span>{e.status_label}</span>
                                    <ArrowRight size={14} />
                                </Link>
                            ))
                        ) : (
                            <p className="hr-empty">Belum ada pegawai di direktori aktif.</p>
                        )}
                    </section>
                </div>
                <p className="ep-bottom-note">
                    Angka di halaman ini berasal dari database. Buka Rekrutmen → Jadwal interview
                    untuk agenda dan undangan.
                </p>
            </main>
        </HrShell>
    );
}
