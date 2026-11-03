import { Head, Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, CalendarDays, Menu, Users, UserRound, X } from 'lucide-react';
import type { HrUser } from './hr-shell';
import '../css/hr.css';

export default function PortalShell({
    title,
    user,
    active,
    children,
}: {
    title: string;
    user: HrUser;
    active: 'me' | 'team' | 'leave' | 'approvals' | 'attendance';
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const links = [
        { key: 'me', href: '/me', label: 'Data saya', icon: UserRound },
        { key: 'leave', href: '/leave', label: 'Cuti & izin saya', icon: CalendarDays },
        { key: 'attendance', href: '/attendance', label: 'Kehadiran saya', icon: CalendarDays },
        ...(user.role === 'supervisor'
            ? [
                  { key: 'team', href: '/team', label: 'Tim saya', icon: Users },
                  {
                      key: 'approvals',
                      href: '/leave?scope=team',
                      label: 'Persetujuan tim',
                      icon: CalendarDays,
                  },
              ]
            : []),
    ];
    return (
        <div className="hr-app">
            <Head title={title}>
                <meta
                    name="description"
                    content="Portal pegawai HRIS Lite untuk data pribadi, cuti, dan kehadiran."
                />
            </Head>
            <a className="hr-skip" href="#hr-main">
                Lewati ke konten
            </a>
            {open && (
                <button
                    className="hr-overlay"
                    aria-label="Tutup navigasi"
                    onClick={() => setOpen(false)}
                />
            )}
            <aside className={`hr-sidebar ${open ? 'is-open' : ''}`} aria-label="Navigasi pegawai">
                <div className="hr-workspace">
                    <span className="hr-mark">H</span>
                    <div>
                        <strong>HRIS Lite</strong>
                        <small>Ruang pegawai</small>
                    </div>
                    <button
                        className="hr-icon hr-close-nav"
                        aria-label="Tutup navigasi"
                        onClick={() => setOpen(false)}
                    >
                        <X size={17} />
                    </button>
                </div>
                <nav>
                    <p>Ruang pegawai</p>
                    {links.map(({ key, href, label, icon: Icon }) => (
                        <Link
                            key={key}
                            href={href}
                            className={key === active ? 'active' : ''}
                            aria-current={key === active ? 'page' : undefined}
                            onClick={() => setOpen(false)}
                        >
                            <Icon size={17} />
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="hr-sidebar-bottom">
                    <Link href="/">
                        <ArrowLeft size={16} />
                        Kembali ke beranda
                    </Link>
                    <div className="hr-profile">
                        <div>
                            <strong>{user.name}</strong>
                            <small>{user.role === 'supervisor' ? 'Atasan' : 'Pegawai'}</small>
                        </div>
                    </div>
                    <Link href="/logout" method="post" as="button" className="hr-logout">
                        Keluar dari akun
                    </Link>
                </div>
            </aside>
            <div className="hr-body">
                <header className="hr-topbar">
                    <div>
                        <button
                            className="hr-icon hr-menu"
                            aria-label="Buka navigasi"
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
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
