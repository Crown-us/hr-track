import { Check, Clock3 } from 'lucide-react';
import { cn } from '../../lib/utils';

export type TimelineItem = {
    label: string;
    time: string;
    state: 'done' | 'current' | 'pending';
    note: string;
};

export function ProcessTimeline({ items }: { items: TimelineItem[] }) {
    return (
        <ol className="py-2">
            {items.map((item, index) => (
                <li
                    key={item.label}
                    aria-current={item.state === 'current' ? 'step' : undefined}
                    className="relative flex gap-4 pb-7 last:pb-0"
                >
                    {index < items.length - 1 && (
                        <span
                            aria-hidden="true"
                            className={cn(
                                'absolute top-8 bottom-2 left-3 w-px',
                                item.state === 'done' ? 'bg-sky-600' : 'bg-slate-200',
                            )}
                        />
                    )}
                    <span
                        aria-hidden="true"
                        className={cn(
                            'relative z-10 grid size-6 shrink-0 place-items-center rounded-full border-2',
                            item.state === 'done' && 'border-sky-700 bg-sky-700 text-white',
                            item.state === 'current' && 'border-sky-700 bg-white text-sky-700',
                            item.state === 'pending' && 'border-slate-300 bg-white text-slate-400',
                        )}
                    >
                        {item.state === 'done' ? <Check size={13} /> : <Clock3 size={13} />}
                    </span>
                    <div className="min-w-0">
                        <p className="text-xs leading-6 text-slate-500">{item.time}</p>
                        <h3 className="mt-1 text-sm font-semibold text-slate-900">
                            {item.label}
                            <span className="sr-only">
                                {' '}
                                —{' '}
                                {item.state === 'done'
                                    ? 'selesai'
                                    : item.state === 'current'
                                      ? 'tahap aktif'
                                      : 'belum dimulai'}
                            </span>
                        </h3>
                        <p className="mt-1.5 text-xs leading-6 text-slate-600">{item.note}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}
