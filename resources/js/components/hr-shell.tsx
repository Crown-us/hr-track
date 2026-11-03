import { Head, Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CalendarDays,
    FileCheck2,
    GraduationCap,
    LayoutDashboard,
    List,
    Menu,
    Users,
    X,
} from 'lucide-react';
import { initials } from '../data/hr-preview';
import '../css/hr.css';

export type HrUser = { name: string; email: string; role: string };
export type HrView = 'dashboard' | 'employees' | 'recruitment';

export default function HrShell({
    title,
    active,
    user,
    onNavigate,
    children,
}: {
    title: string;
    active:
        | HrView
        | 'training'
        | 'contracts'
        | 'reports'
        | 'accounts'
        | 'leave'
        | 'attendance'
        | 'applicants'
        | 'departments'
        | 'positions';
    user?: HrUser | null;
    onNavigate?: (view: HrView) => void;
    children: ReactNode;
}) {
    const [sidebar, setSidebar] = useState(false);
    const roles: Record<string, string> = {
        admin: 'Admin',
        hr_staff: 'Staf HR',
        viewer: 'Pembaca',
    };
    const navigation = [
        { id: 'dashboard' as const, label: 'Ringkasan', href: '/hr', icon: LayoutDashboard },
        { id: 'employees' as const, label: 'Pegawai', href: '/employees', icon: Users },
        {
            id: 'recruitment' as const,
            label: 'Rekrutmen',
            href: '/vacancies',
            icon: BriefcaseBusiness,
        },
    ];
    return (
        <div className="hr-app">
            <Head title={title}>
                <meta
                    name="description"
                    content="Ruang kerja HRIS Lite untuk data pegawai, rekrutmen, cuti, dan kehadiran."
                />
            </Head>
            <a className="hr-skip" href="#hr-main">
                Lewati ke konten
            </a>
            {sidebar && (
                <button
                    className="hr-overlay"
                    aria-label="Tutup navigasi"
                    onClick={() => setSidebar(false)}
                />
            )}
            <aside
                className={`hr-sidebar ${sidebar ? 'is-open' : ''}`}
                aria-label="Navigasi ruang kerja HR"
            >
                <div className="hr-workspace">
                    <span className="hr-mark">H</span>
                    <div>
                        <strong>HRIS Lite</strong>
                        <small>Ruang kerja HR</small>
                    </div>
                    <button
                        className="hr-icon hr-close-nav"
                        aria-label="Tutup navigasi"
                        onClick={() => setSidebar(false)}
                    >
                        <X size={17} />
                    </button>
                </div>
                <nav>
                    <p>Ruang kerja</p>
                    {navigation.map(({ id, label, href, icon: Icon }) =>
                        onNavigate ? (
                            <button
                                key={id}
                                className={active === id ? 'active' : ''}
                                aria-current={active === id ? 'page' : undefined}
                                onClick={() => {
                                    onNavigate(id);
                                    setSidebar(false);
                                }}
                            >
                                <Icon size={17} />
                                {label}
                            </button>
                        ) : (
                            <Link
                                key={id}
                                href={href}
                                className={active === id ? 'active' : ''}
                                aria-current={active === id ? 'page' : undefined}
                                onClick={() => setSidebar(false)}
                            >
                                <Icon size={17} />
                                {label}
                            </Link>
                        ),
                    )}
                    {user && !onNavigate && (
                        <>
                            <p>Data pendukung</p>
                            <Link
                                href="/applicants"
                                className={active === 'applicants' ? 'active' : ''}
                                aria-current={active === 'applicants' ? 'page' : undefined}
                                onClick={() => setSidebar(false)}
                            >
                                <Users size={17} />
                                Profil pelamar
                            </Link>
                            <Link
                                href="/departments"
                                className={active === 'departments' ? 'active' : ''}
                                aria-current={active === 'departments' ? 'page' : undefined}
                                onClick={() => setSidebar(false)}
                            >
                                <List size={17} />
                                Departemen
                            </Link>
                            <Link
                                href="/positions"
                                className={active === 'positions' ? 'active' : ''}
                                aria-current={active === 'positions' ? 'page' : undefined}
                                onClick={() => setSidebar(false)}
                            >
                                <BriefcaseBusiness size={17} />
                                Jabatan
                            </Link>
                        </>
                    )}
                    <p>Pengembangan tim</p>
                    {user && ['admin', 'hr_staff'].includes(user.role) && !onNavigate && (
                        <Link
                            href="/attendance"
                            className={active === 'attendance' ? 'active' : ''}
                            aria-current={active === 'attendance' ? 'page' : undefined}
                            onClick={() => setSidebar(false)}
                        >
                            <CalendarDays size={17} />
                            Absensi
                        </Link>
                    )}
                    {user && ['admin', 'hr_staff'].includes(user.role) && !onNavigate && (
                        <Link
                            href="/leave"
                            className={active === 'leave' ? 'active' : ''}
                            aria-current={active === 'leave' ? 'page' : undefined}
                            onClick={() => setSidebar(false)}
                        >
                            <CalendarDays size={17} />
                            Cuti & izin
                        </Link>
                    )}
                    {user?.role === 'admin' && !onNavigate && (
                        <Link
                            href="/accounts"
                            className={active === 'accounts' ? 'active' : ''}
                            aria-current={active === 'accounts' ? 'page' : undefined}
                            onClick={() => setSidebar(false)}
                        >
                            <Users size={17} />
                            Kelola akun
                        </Link>
                    )}
                    {user && !onNavigate ? (
                        <Link
                            href="/trainings"
                            className={active === 'training' ? 'active' : ''}
                            aria-current={active === 'training' ? 'page' : undefined}
                            onClick={() => setSidebar(false)}
                        >
                            <GraduationCap size={17} />
                            Pelatihan
                        </Link>
                    ) : (
                        <button disabled>
                            <GraduationCap size={17} />
                            Pelatihan<small>Segera</small>
                        </button>
                    )}
                    {user && !onNavigate ? (
                        <Link
                            href="/contracts"
                            className={active === 'contracts' ? 'active' : ''}
                            aria-current={active === 'contracts' ? 'page' : undefined}
                            onClick={() => setSidebar(false)}
                        >
                            <FileCheck2 size={17} />
                            Hubungan kerja
                        </Link>
                    ) : (
                        <button disabled>
                            <FileCheck2 size={17} />
                            Hubungan kerja<small>Segera</small>
                        </button>
                    )}
                    {user && !onNavigate ? (
                        ['admin', 'hr_staff'].includes(user.role) && (
                            <Link
                                href="/reports"
                                className={active === 'reports' ? 'active' : ''}
                                aria-current={active === 'reports' ? 'page' : undefined}
                                onClick={() => setSidebar(false)}
                            >
                                <List size={17} />
                                Laporan
                            </Link>
                        )
                    ) : (
                        <button disabled>
                            <List size={17} />
                            Laporan<small>Segera</small>
                        </button>
                    )}
                </nav>
                <div className="hr-sidebar-bottom">
                    <Link href="/">
                        <ArrowLeft size={16} />
                        Kembali ke beranda
                    </Link>
                    <div className="hr-profile">
                        <span className="hr-avatar">{user ? initials(user.name) : 'HR'}</span>
                        <div>
                            <strong>{user?.name ?? 'Tim Human Resources'}</strong>
                            <small>{user ? roles[user.role] : 'Akun contoh'}</small>
                        </div>
                    </div>
                    {user ? (
                        <Link href="/logout" method="post" as="button" className="hr-logout">
                            Keluar dari akun
                        </Link>
                    ) : (
                        <Link href="/login">Masuk ke akun</Link>
                    )}
                </div>
            </aside>
            <div className="hr-body">
                <header className="hr-topbar">
                    <div>
                        <button
                            className="hr-icon hr-menu"
                            aria-label="Buka navigasi"
                            aria-expanded={sidebar}
                            onClick={() => setSidebar(!sidebar)}
                        >
                            <Menu size={18} />
                        </button>
                        <strong>{title}</strong>
                    </div>
                    <span className="hr-help">HRIS Lite</span>
                </header>
                {children}
            </div>
        </div>
    );
}
