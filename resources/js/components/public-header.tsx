import { ArrowUpRight } from 'lucide-react';

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
                        Lowongan
                    </a>
                </nav>
                <a href="/login" className="taste-nav-access">
                    Masuk HR <ArrowUpRight size={16} aria-hidden="true" />
                </a>
            </header>
        </div>
    );
}
