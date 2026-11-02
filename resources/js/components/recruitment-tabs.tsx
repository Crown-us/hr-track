import { Link } from '@inertiajs/react';

export default function RecruitmentTabs({ active }: { active: 'vacancies' | 'pipeline' }) {
    return (
        <nav className="hr-recruitment-tabs" aria-label="Menu rekrutmen">
            <Link href="/vacancies" aria-current={active === 'vacancies' ? 'page' : undefined}>
                Lowongan
            </Link>
            <Link href="/applications" aria-current={active === 'pipeline' ? 'page' : undefined}>
                Pipeline kandidat
            </Link>
        </nav>
    );
}
