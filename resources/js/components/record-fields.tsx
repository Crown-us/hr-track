import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
export function RecordField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="ep-field tr-field">
            <span>{label}</span>
            {children}
            {error && (
                <small className="ep-error" role="alert">
                    {error}
                </small>
            )}
        </label>
    );
}
export function RecordErrors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {['version', 'case', 'limit'].map(
                (k) =>
                    errors[k] && (
                        <p role="alert" className="ep-alert" key={k}>
                            {errors[k]}
                        </p>
                    ),
            )}
        </>
    );
}
export function RecordPagination({
    total,
    links,
}: {
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}) {
    return total > 15 ? (
        <nav className="tr-pagination" aria-label="Halaman daftar">
            {links.map((l, i) =>
                l.url ? (
                    <Link key={i} href={l.url} aria-current={l.active ? 'page' : undefined}>
                        {i === 0 ? 'Sebelumnya' : i === links.length - 1 ? 'Berikutnya' : l.label}
                    </Link>
                ) : (
                    <span key={i}>
                        {i === 0 ? 'Sebelumnya' : i === links.length - 1 ? 'Berikutnya' : l.label}
                    </span>
                ),
            )}
        </nav>
    ) : null;
}
