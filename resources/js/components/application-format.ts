import type { StatusVariant } from './ui/status-badge';
export const applicationTone: Record<string, StatusVariant> = {
    screening: 'review',
    interview: 'progress',
    offering: 'pending',
    accepted: 'success',
    rejected: 'failed',
};
export const applicationDate = (value: string, time = false) =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        ...(time ? { timeStyle: 'short', timeZone: 'Asia/Jakarta' } : { timeZone: 'UTC' }),
    }).format(new Date(time ? value : `${value}T00:00:00Z`));
