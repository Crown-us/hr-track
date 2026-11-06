import { Head, Link } from '@inertiajs/react';
import { Fragment, useState, type ReactNode } from 'react';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    BriefcaseBusiness,
    CalendarClock,
    CalendarDays,
    ChartColumn,
    ContactRound,
    FileCheck2,
    GraduationCap,
    LayoutDashboard,
    Menu,
    ScrollText,
    ShieldCheck,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { initials } from '../data/hr-preview';
import '../css/hr.css';

export type HrUser = { name: string; email: string; role: string };
export type HrView = 'dashboard' | 'employees' | 'recruitment';
export type HrActive =
    | HrView
    | 'attendance'
    | 'leave'
    | 'training'
    | 'contracts'
    | 'applicants'
    | 'departments'
    | 'positions'
    | 'reports'
    | 'accounts'
    | 'audit';

type NavItem = {
    id: HrActive;
    label: string;
    href: string;
    icon: LucideIcon;
    /** Preview-mode view key; the item then always shows, as a button when `onNavigate` is set. */
    view?: HrView;
    /** Roles allowed to see the item. Omitted means every signed-in HR user. */
    roles?: string[];
    /** Shown as a disabled "Segera" entry in the signed-out preview. */
    soon?: boolean;
};

const hrRoles = ['admin', 'hr_staff'];
const navGroups: { title: string; items: NavItem[] }[] = [
    {
        title: 'Ruang kerja',
        items: [
            {
                id: 'dashboard',
                label: 'Ringkasan',
                href: '/hr',
                icon: LayoutDashboard,
                view: 'dashboard',
            },
            {
                id: 'employees',
                label: 'Pegawai',
                href: '/employees',
                icon: Users,
                view: 'employees',
            },
            {
                id: 'recruitment',
                label: 'Rekrutmen',
                href: '/vacancies',
                icon: BriefcaseBusiness,
                view: 'recruitment',
            },
        ],
    },
    {
        title: 'Operasional',
        items: [
            {
                id: 'attendance',
                label: 'Absensi',
                href: '/attendance',
                icon: CalendarClock,
                roles: hrRoles,
            },
            {
                id: 'leave',
                label: 'Cuti & izin',
                href: '/leave',
                icon: CalendarDays,
                roles: hrRoles,
            },
            {
                id: 'training',
                label: 'Pelatihan',
                href: '/trainings',
                icon: GraduationCap,
                soon: true,
            },
            {
                id: 'contracts',
                label: 'Hubungan kerja',
                href: '/contracts',
                icon: FileCheck2,
                soon: true,
            },
        ],
    },
    {
        title: 'Data pendukung',
        items: [
            { id: 'applicants', label: 'Profil pelamar', href: '/applicants', icon: ContactRound },
            { id: 'departments', label: 'Departemen', href: '/departments', icon: Building2 },
            { id: 'positions', label: 'Jabatan', href: '/positions', icon: BadgeCheck },
        ],
    },
    {
        title: 'Laporan & kontrol',
        items: [
            {
                id: 'reports',
                label: 'Laporan',
                href: '/reports',
                icon: ChartColumn,
                roles: hrRoles,
                soon: true,
            },
            {
                id: 'accounts',
                label: 'Kelola akun',
                href: '/accounts',
                icon: ShieldCheck,
                roles: ['admin'],
            },
            {
                id: 'audit',
                label: 'Jejak audit',
                href: '/audit',
                icon: ScrollText,
                roles: ['admin'],
            },
        ],
    },
];

export default function HrShell({
    title,
    active,
    user,
    onNavigate,
    children,
}: {
    title: string;
    active: HrActive;
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
    const signedIn = Boolean(user) && !onNavigate;
    const renderItem = ({ id, label, href, icon: Icon, view, roles: allowed, soon }: NavItem) => {
        const current = active === id;
        if (view && onNavigate) {
            return (
                <button
                    key={id}
                    className={current ? 'active' : ''}
                    aria-current={current ? 'page' : undefined}
                    onClick={() => {
                        onNavigate(view);
                        setSidebar(false);
                    }}
                >
                    <Icon size={17} />
                    {label}
                </button>
            );
        }
        if (!view && !signedIn) {
            return soon ? (
                <button key={id} disabled>
                    <Icon size={17} />
                    {label}
                    <small>Segera</small>
                </button>
            ) : null;
        }
        if (allowed && !(user && allowed.includes(user.role))) return null;
        return (
            <Link
                key={id}
                href={href}
                className={current ? 'active' : ''}
                aria-current={current ? 'page' : undefined}
                onClick={() => setSidebar(false)}
            >
                <Icon size={17} />
                {label}
            </Link>
        );
    };
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
                    {navGroups.map(({ title, items }) => {
                        const links = items.map(renderItem).filter(Boolean);
                        return links.length ? (
                            <Fragment key={title}>
                                <p>{title}</p>
                                {links}
                            </Fragment>
                        ) : null;
                    })}
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
