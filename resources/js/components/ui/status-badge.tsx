import { CircleCheck, CircleDashed, CircleX, Clock3, Scan, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '../../lib/utils';

const variants = {
    pending: { icon: TriangleAlert, style: 'bg-orange-50 text-orange-700', label: 'Menunggu' },
    failed: { icon: CircleX, style: 'bg-rose-50 text-rose-700', label: 'Ditolak' },
    success: { icon: CircleCheck, style: 'bg-emerald-50 text-emerald-700', label: 'Selesai' },
    progress: { icon: CircleDashed, style: 'bg-sky-50 text-sky-700', label: 'Diproses' },
    review: { icon: Scan, style: 'bg-amber-50 text-amber-800', label: 'Ditinjau' },
    expired: { icon: Clock3, style: 'bg-slate-100 text-slate-600', label: 'Berakhir' },
    submitted: { icon: Clock3, style: 'bg-violet-50 text-violet-700', label: 'Diajukan' },
} as const;

export type StatusVariant = keyof typeof variants;

export function StatusBadge({
    variant,
    children,
    className,
}: {
    variant: StatusVariant;
    children?: ReactNode;
    className?: string;
}) {
    const { icon: Icon, style, label } = variants[variant];
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full px-3 py-1.5 text-xs leading-4 font-medium whitespace-nowrap',
                style,
                className,
            )}
        >
            <Icon size={14} strokeWidth={1.8} aria-hidden="true" />
            {children ?? label}
        </span>
    );
}

export function StatusPalette() {
    return (
        <div className="flex flex-wrap gap-2.5" aria-label="Contoh status proses">
            {(Object.keys(variants) as StatusVariant[]).map((variant) => (
                <StatusBadge key={variant} variant={variant} className="min-w-28" />
            ))}
        </div>
    );
}
