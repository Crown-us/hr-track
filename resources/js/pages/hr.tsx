import { router } from '@inertiajs/react';
import HrShell, { type HrUser } from '../components/hr-shell';
import HrDashboard from '../components/hr-dashboard';
import type { RecruitmentSummary } from '../types/applications';
import RecruitmentTabs from '../components/recruitment-tabs';
import type { VacancyRecord } from '../types/vacancies';
import type { EmployeeRecord } from '../types/employees';
import type { ContractSummary } from '../types/contracts';
import { useRef, useState } from 'react';
import {
    ArrowRight,
    BriefcaseBusiness,
    CalendarDays,
    ChevronRight,
    LayoutGrid,
    List,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import {
    candidates,
    employees,
    initials,
    stages,
    vacancies,
    type Candidate,
} from '../data/hr-preview';
import { StatusBadge, type StatusVariant } from '../components/ui/status-badge';
import { ProcessTimeline } from '../components/ui/process-timeline';
import '../css/hr.css';

type View = 'dashboard' | 'employees' | 'recruitment';
const titles: Record<View, string> = {
    dashboard: 'Ringkasan HR',
    employees: 'Pegawai',
    recruitment: 'Rekrutmen',
};
const tones: Record<string, StatusVariant> = {
    Screening: 'review',
    Interview: 'progress',
    Offering: 'pending',
    Diterima: 'success',
    Ditolak: 'failed',
};

export default function HrPreview({
    auth,
    initialView = 'dashboard',
    employeeSummary,
    vacancySummary,
    recruitmentSummary,
    contractSummary,
}: {
    auth?: { user: HrUser | null };
    initialView?: View;
    employeeSummary?: { count: number; recent: EmployeeRecord[] };
    vacancySummary?: { count: number; recent: VacancyRecord[] };
    recruitmentSummary?: RecruitmentSummary;
    contractSummary?: ContractSummary;
}) {
    const user = auth?.user;
    const [view, setView] = useState<View>(initialView);
    const [query, setQuery] = useState('');
    const [department, setDepartment] = useState('Semua departemen');
    const [job, setJob] = useState('Semua lowongan');
    const [board, setBoard] = useState(true);
    const [candidate, setCandidate] = useState<Candidate | null>(null);
    const dialog = useRef<HTMLDialogElement>(null);
    const changeView = (next: View) => {
        if (next === 'recruitment' && user) {
            router.get('/vacancies');
            return;
        }
        if (next === 'employees' && user) {
            router.get('/employees');
            return;
        }
        setView(next);
        setQuery('');
    };
    const openCandidate = (person: Candidate) => {
        setCandidate(person);
        dialog.current?.showModal();
    };
    const filtered = candidates.filter(
        (person) =>
            `${person.name} ${person.job}`.toLowerCase().includes(query.toLowerCase()) &&
            (job === 'Semua lowongan' || person.job === job),
    );
    const directory = employeeSummary
        ? employeeSummary.recent.map((person) => ({
              id: person.employee_number,
              name: person.full_name,
              position: person.position,
              team: person.department,
              joined: new Intl.DateTimeFormat('id-ID', {
                  dateStyle: 'medium',
                  timeZone: 'UTC',
              }).format(new Date(person.join_date + 'T00:00:00Z')),
              status: person.status_label,
          }))
        : employees;
    const people = directory.filter(
        (person) =>
            `${person.name} ${person.id} ${person.position}`
                .toLowerCase()
                .includes(query.toLowerCase()) &&
            (department === 'Semua departemen' || person.team === department),
    );
    const interviews = candidates.filter((person) => person.interview);
    const screening = candidates.filter((person) => person.stage === 'Screening');
    const dashboardVacancies: {
        id?: number;
        title: string;
        location: string;
        typeLabel?: string;
    }[] = vacancySummary
        ? vacancySummary.recent.map((item) => ({
              id: item.id,
              title: item.job_title,
              location: item.location,
              typeLabel: item.type_label,
          }))
        : vacancies;
    const metrics = [
        [
            'Pegawai',
            employeeSummary?.count ?? employees.length,
            employeeSummary ? 'Dalam direktori aktif' : 'Dalam direktori contoh',
        ],
        [
            'Lowongan aktif',
            vacancySummary?.count ?? vacancies.length,
            vacancySummary ? 'Dalam periode rekrutmen' : 'Posisi contoh sedang dibuka',
        ],
        ['Pelamar', candidates.length, `${screening.length} menunggu screening`],
        ['Wawancara hari ini', interviews.length, 'Senin, 2 November 2026'],
    ] as const;
    const employeeTable = (limited = false) => (
        <div className="hr-table-scroll">
            <table>
                <caption className="sr-only">Direktori pegawai</caption>
                <thead>
                    <tr>
                        <th>Nama pegawai</th>
                        <th>Departemen</th>
                        <th>Jabatan</th>
                        {!limited && <th>Bergabung</th>}
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {(limited ? directory.slice(0, 4) : people).map((person) => (
                        <tr key={person.id}>
                            <td>
                                <div className="hr-person">
                                    <span className="hr-avatar">{initials(person.name)}</span>
                                    <div>
                                        <strong>{person.name}</strong>
                                        <small>{person.id}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{person.team}</td>
                            <td>{person.position}</td>
                            {!limited && <td>{person.joined}</td>}
                            <td>
                                <StatusBadge
                                    variant={
                                        person.status === 'Tetap'
                                            ? 'success'
                                            : person.status === 'Kontrak'
                                              ? 'progress'
                                              : 'submitted'
                                    }
                                >
                                    {person.status}
                                </StatusBadge>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {!people.length && (
                <div className="hr-empty">
                    Tidak ada pegawai yang cocok. Coba kata kunci atau departemen lain.
                </div>
            )}
        </div>
    );
    if (user && recruitmentSummary) {
        return (
            <HrDashboard
                user={user}
                employees={employeeSummary}
                vacancies={vacancySummary}
                recruitment={recruitmentSummary}
                contracts={contractSummary}
            />
        );
    }
    return (
        <HrShell title={titles[view]} active={view} user={user} onNavigate={changeView}>
            <div className="hr-preview-banner" id="preview-note">
                <span className="hr-preview-dot" />
                {user ? 'Ringkasan kandidat dan wawancara' : 'Pratinjau ruang kerja HR'}
                <span className="hr-banner-note">
                    {user
                        ? 'Pegawai dan lowongan tersimpan di database'
                        : 'Belum terhubung ke data operasional'}
                </span>
            </div>
            <main id="hr-main" className="hr-main">
                {view === 'recruitment' && user && <RecruitmentTabs active="pipeline" />}
                <div className="hr-page-heading">
                    <div>
                        <h1>{titles[view]}</h1>
                        <p>
                            {view === 'dashboard'
                                ? `Senin, 2 November 2026 · Ada ${screening.length} berkas yang perlu ditinjau.`
                                : view === 'employees'
                                  ? 'Kenali orang-orang di balik pekerjaan tim.'
                                  : 'Ikuti perjalanan kandidat, dari berkas hingga bergabung.'}
                        </p>
                    </div>
                    {view === 'dashboard' && (
                        <button className="hr-button" onClick={() => changeView('recruitment')}>
                            Buka rekrutmen <ArrowRight size={15} />
                        </button>
                    )}
                </div>
                {view !== 'dashboard' && (
                    <div className="hr-toolbar">
                        <label className="hr-search">
                            <Search size={16} />
                            <span className="sr-only">
                                Cari {view === 'employees' ? 'pegawai' : 'kandidat'}
                            </span>
                            <input
                                aria-label={view === 'employees' ? 'Cari pegawai' : 'Cari kandidat'}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder={
                                    view === 'employees'
                                        ? 'Cari nama, jabatan, atau ID…'
                                        : 'Cari kandidat atau posisi…'
                                }
                            />
                            {query && (
                                <button
                                    className="hr-icon"
                                    aria-label="Hapus pencarian"
                                    onClick={() => setQuery('')}
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </label>
                        <label className="hr-filter">
                            <SlidersHorizontal size={15} />
                            <span className="sr-only">
                                Filter {view === 'employees' ? 'departemen' : 'lowongan'}
                            </span>
                            {view === 'employees' ? (
                                <select
                                    value={department}
                                    onChange={(event) => setDepartment(event.target.value)}
                                >
                                    <option>Semua departemen</option>
                                    {[...new Set(employees.map((person) => person.team))].map(
                                        (team) => (
                                            <option key={team}>{team}</option>
                                        ),
                                    )}
                                </select>
                            ) : (
                                <select
                                    value={job}
                                    onChange={(event) => setJob(event.target.value)}
                                >
                                    <option>Semua lowongan</option>
                                    {vacancies.map((vacancy) => (
                                        <option key={vacancy.title}>{vacancy.title}</option>
                                    ))}
                                </select>
                            )}
                        </label>
                        {view === 'recruitment' && (
                            <button className="hr-button" onClick={() => setBoard(!board)}>
                                {board ? <List size={15} /> : <LayoutGrid size={15} />}{' '}
                                {board ? 'Daftar' : 'Board'}
                            </button>
                        )}
                        <span className="hr-result-count">
                            {view === 'employees' ? people.length : filtered.length}{' '}
                            {view === 'employees' ? 'pegawai' : 'kandidat'}
                        </span>
                    </div>
                )}
                {view === 'dashboard' && (
                    <>
                        <section className="hr-metrics" aria-label="Ringkasan data contoh">
                            {metrics.map(([label, value, note]) => (
                                <div key={label}>
                                    <p>{label}</p>
                                    <strong>{value}</strong>
                                    <small>{note}</small>
                                </div>
                            ))}
                        </section>
                        <div className="hr-overview-grid">
                            <section className="hr-panel hr-agenda">
                                <div className="hr-panel-title">
                                    <h2>Wawancara hari ini</h2>
                                    <span>{interviews.length} terjadwal · WIB</span>
                                </div>
                                {interviews.map((person) => (
                                    <button
                                        className="hr-agenda-row"
                                        key={person.id}
                                        onClick={() => openCandidate(person)}
                                    >
                                        <time>
                                            {person.interview}
                                            <small>45 menit</small>
                                        </time>
                                        <span className="hr-avatar">{initials(person.name)}</span>
                                        <span>
                                            <strong>{person.name}</strong>
                                            <small>{person.job}</small>
                                        </span>
                                        <span className="hr-agenda-kind">Wawancara HR</span>
                                        <ChevronRight size={16} />
                                    </button>
                                ))}
                                <p className="hr-footnote">
                                    <CalendarDays size={14} /> Jadwal contoh untuk 2 November 2026
                                </p>
                            </section>
                            <section className="hr-panel">
                                <div className="hr-panel-title">
                                    <h2>Pipeline kandidat</h2>
                                    <button
                                        onClick={() =>
                                            user
                                                ? router.get('/hr?view=recruitment')
                                                : changeView('recruitment')
                                        }
                                    >
                                        Lihat board <ArrowRight size={14} />
                                    </button>
                                </div>
                                <div className="hr-pipeline-summary">
                                    {stages.map((stage) => {
                                        const count = candidates.filter(
                                            (person) => person.stage === stage,
                                        ).length;
                                        return (
                                            <div key={stage}>
                                                <span>{stage}</span>
                                                <div className="hr-bar-track">
                                                    <div
                                                        style={{
                                                            width: `${(count / candidates.length) * 100}%`,
                                                        }}
                                                    />
                                                </div>
                                                <strong>{count}</strong>
                                            </div>
                                        );
                                    })}
                                </div>
                                <p className="hr-footnote">
                                    {candidates.length} lamaran pada {vacancies.length} lowongan
                                </p>
                            </section>
                        </div>
                        <div className="hr-overview-grid hr-lower-grid">
                            <section className="hr-panel">
                                <div className="hr-panel-title">
                                    <h2>Perlu ditinjau</h2>
                                    <span>{screening.length} kandidat</span>
                                </div>
                                {screening.map((person) => (
                                    <button
                                        className="hr-review-row"
                                        key={person.id}
                                        onClick={() => openCandidate(person)}
                                    >
                                        <span className="hr-avatar">{initials(person.name)}</span>
                                        <span>
                                            <strong>{person.name}</strong>
                                            <small>
                                                {person.job} · {person.applied}
                                            </small>
                                        </span>
                                        <StatusBadge variant="review">Screening</StatusBadge>
                                        <ChevronRight size={15} />
                                    </button>
                                ))}
                            </section>
                            <section className="hr-panel">
                                <div className="hr-panel-title">
                                    <h2>Lowongan aktif</h2>
                                    <span>{vacancySummary?.count ?? vacancies.length} posisi</span>
                                </div>
                                {dashboardVacancies.map((vacancy) => (
                                    <button
                                        key={vacancy.id ?? vacancy.title}
                                        className="hr-vacancy-row"
                                        onClick={() => {
                                            if (vacancy.id) {
                                                router.get(`/vacancies/${vacancy.id}`);
                                                return;
                                            }
                                            setJob(vacancy.title);
                                            changeView('recruitment');
                                        }}
                                    >
                                        <span>
                                            <strong>{vacancy.title}</strong>
                                            <small>{vacancy.location}</small>
                                        </span>
                                        {vacancy.typeLabel ? (
                                            <span>{vacancy.typeLabel}</span>
                                        ) : (
                                            <span>
                                                {
                                                    candidates.filter(
                                                        (person) => person.job === vacancy.title,
                                                    ).length
                                                }{' '}
                                                pelamar
                                            </span>
                                        )}
                                        <ChevronRight size={15} />
                                    </button>
                                ))}
                                {vacancySummary && !dashboardVacancies.length && (
                                    <p className="hr-empty">
                                        Belum ada lowongan aktif.{' '}
                                        <button
                                            className="hr-text-button"
                                            onClick={() => router.get('/vacancies')}
                                        >
                                            Kelola lowongan
                                        </button>
                                    </p>
                                )}
                            </section>
                        </div>
                        <section className="hr-panel hr-directory-preview">
                            <div className="hr-panel-title">
                                <h2>Direktori pegawai</h2>
                                <button onClick={() => changeView('employees')}>
                                    Lihat semua <ArrowRight size={14} />
                                </button>
                            </div>
                            {employeeTable(true)}
                        </section>
                    </>
                )}
                {view === 'employees' && (
                    <section className="hr-panel">
                        {employeeTable()}
                        <div className="hr-table-footer">
                            Menampilkan {people.length} dari {employees.length} pegawai contoh
                        </div>
                    </section>
                )}
                {view === 'recruitment' &&
                    (board ? (
                        <div className="hr-board-frame">
                            <div className="hr-board">
                                {stages.map((stage) => (
                                    <section
                                        className="hr-board-column"
                                        key={stage}
                                        aria-label={stage}
                                    >
                                        <div className="hr-column-heading">
                                            <span
                                                className={`hr-stage-dot stage-${stage.toLowerCase()}`}
                                            />
                                            <h2>{stage}</h2>
                                            <span>
                                                {
                                                    filtered.filter(
                                                        (person) => person.stage === stage,
                                                    ).length
                                                }
                                            </span>
                                        </div>
                                        {filtered
                                            .filter((person) => person.stage === stage)
                                            .map((person) => (
                                                <button
                                                    className="hr-candidate-card"
                                                    key={person.id}
                                                    onClick={() => openCandidate(person)}
                                                >
                                                    <div className="hr-card-heading">
                                                        <span className="hr-avatar">
                                                            {initials(person.name)}
                                                        </span>
                                                        <span>{person.id}</span>
                                                        <ChevronRight size={14} />
                                                    </div>
                                                    <h3>{person.name}</h3>
                                                    <p>
                                                        <BriefcaseBusiness size={14} />
                                                        {person.job}
                                                    </p>
                                                    <p>
                                                        <CalendarDays size={14} />
                                                        {person.applied}
                                                    </p>
                                                    <div className="hr-card-bottom">
                                                        <StatusBadge variant={tones[stage]}>
                                                            {stage}
                                                        </StatusBadge>
                                                    </div>
                                                </button>
                                            ))}
                                        {!filtered.some((person) => person.stage === stage) && (
                                            <p className="hr-column-empty">
                                                Belum ada kandidat di tahap ini untuk filter yang
                                                dipilih.
                                            </p>
                                        )}
                                    </section>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <section className="hr-panel">
                            <div className="hr-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Kandidat</th>
                                            <th>Posisi</th>
                                            <th>Tanggal melamar</th>
                                            <th>Tahap</th>
                                            <th>
                                                <span className="sr-only">Aksi</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filtered.map((person) => (
                                            <tr key={person.id}>
                                                <td>
                                                    <strong>{person.name}</strong>
                                                    <small>{person.email}</small>
                                                </td>
                                                <td>{person.job}</td>
                                                <td>{person.applied}</td>
                                                <td>
                                                    <StatusBadge variant={tones[person.stage]}>
                                                        {person.stage}
                                                    </StatusBadge>
                                                </td>
                                                <td>
                                                    <button
                                                        className="hr-text-button"
                                                        onClick={() => openCandidate(person)}
                                                    >
                                                        Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!filtered.length && (
                                    <p className="hr-empty">
                                        Tidak ada kandidat yang cocok. Coba kata kunci lain.
                                    </p>
                                )}
                            </div>
                        </section>
                    ))}
                <p className="hr-bottom-note">
                    {user
                        ? 'Pegawai dan lowongan tersimpan di database. Pipeline kandidat masih contoh.'
                        : 'HRIS Lite · Pratinjau interaktif, perubahan belum dapat disimpan.'}
                </p>
            </main>
            <dialog
                ref={dialog}
                className="hr-detail-dialog"
                aria-labelledby="candidate-title"
                onClose={() => setCandidate(null)}
                onClick={(event) => {
                    if (event.target === event.currentTarget) dialog.current?.close();
                }}
            >
                <div className="hr-dialog-heading">
                    <span>Detail kandidat</span>
                    <button
                        className="hr-icon"
                        aria-label="Tutup detail"
                        onClick={() => dialog.current?.close()}
                    >
                        <X size={19} />
                    </button>
                </div>
                {candidate && (
                    <div className="hr-dialog-content">
                        <span className="hr-avatar hr-avatar-large">
                            {initials(candidate.name)}
                        </span>
                        <h2 id="candidate-title">{candidate.name}</h2>
                        <p>
                            {candidate.job} · {candidate.id}
                        </p>
                        <StatusBadge variant={tones[candidate.stage]}>
                            {candidate.stage}
                        </StatusBadge>
                        <dl>
                            <dt>Email</dt>
                            <dd>{candidate.email}</dd>
                            <dt>Tanggal melamar</dt>
                            <dd>{candidate.applied}</dd>
                            <dt>Catatan HR</dt>
                            <dd>{candidate.note}</dd>
                        </dl>
                        <h3>Perjalanan lamaran</h3>
                        <ProcessTimeline
                            items={[
                                {
                                    label: 'Berkas diterima',
                                    time: candidate.applied,
                                    state: 'done',
                                    note: 'Data lamaran contoh tercatat.',
                                },
                                {
                                    label: candidate.stage,
                                    time: 'Tahap saat ini',
                                    state: 'current',
                                    note: candidate.note,
                                },
                            ]}
                        />
                        <p className="hr-dialog-note">
                            Riwayat kandidat dalam pratinjau ruang kerja. Aksi ubah status, CV, dan
                            penyimpanan tersedia setelah backend diimplementasikan.
                        </p>
                    </div>
                )}
            </dialog>
        </HrShell>
    );
}
