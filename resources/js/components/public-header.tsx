import { usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';

type SessionUser = { role_label: string; home: string };

function AccessLink() {
    const user = usePage<{ auth?: { user: SessionUser | null } }>().props.auth?.user;

    if (!user) {
        return (
            <a href="/login" className="taste-nav-access">
                Masuk <ArrowUpRight size={16} aria-hidden="true" />
            </a>
        );
    }

    const destination = user.home === '/me' ? 'Portal saya' : 'Dasbor HR';

    return (
        <a
            href={user.home}
            className="taste-nav-access taste-nav-access-user"
            aria-label={`${destination}, masuk sebagai ${user.role_label}`}
        >
            <span className="taste-nav-access-text">
                {destination}
                <small>{user.role_label}</small>
            </span>
            <ArrowUpRight size={16} aria-hidden="true" />
        </a>
    );
}

export function PublicHeader({ careers = false }: { careers?: boolean }) {
    const home = careers ? '/' : '';

    return (
        <div className="northstar-header">
            <header className="taste-nav taste-wrap" data-od-id="masthead">
                <a href="/" className="taste-wordmark" aria-label="HRIS Lite, beranda">
                    HRIS<span>Lite</span>
                    <span className="taste-mark" aria-hidden="true">
                        ↗
                    </span>
                </a>
                <nav aria-label="Navigasi utama">
                    <a href={`${home}#fitur`}>Fitur</a>
                    <a href={`${home}#cara-kerja`}>Cara kerja</a>
                    <a href={`${home}#tampilan`}>Tampilan</a>
                    <a href="/karier" aria-current={careers ? 'page' : undefined}>
                        Lowongan <ArrowUpRight size={12} aria-hidden="true" />
                    </a>
                </nav>
                <AccessLink />
            </header>
        </div>
    );
}
