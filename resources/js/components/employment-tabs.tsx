import { Link } from '@inertiajs/react';
export default function EmploymentTabs({
    active,
    canManage,
}: {
    active: 'contracts' | 'discipline';
    canManage: boolean;
}) {
    return (
        <nav className="ep-tabs" aria-label="Hubungan kerja">
            <Link href="/contracts" aria-current={active === 'contracts' ? 'page' : undefined}>
                Kontrak kerja
            </Link>
            {canManage && (
                <Link
                    href="/discipline"
                    aria-current={active === 'discipline' ? 'page' : undefined}
                >
                    Pelanggaran & SP
                </Link>
            )}
        </nav>
    );
}
