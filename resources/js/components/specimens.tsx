import { ChevronDown } from 'lucide-react';
import { cn } from '../lib/utils';
import { StatusBadge } from './ui/status-badge';
import { ProcessTimeline, type TimelineItem } from './ui/process-timeline';

const pipeline = [
    { label: 'Screening', value: 18, width: '53%', className: 'bg-slate-900' },
    { label: 'Interview', value: 9, width: '26%', className: 'bg-slate-600' },
    { label: 'Offering', value: 4, width: '12%', className: 'bg-slate-400' },
    { label: 'Diterima', value: 3, width: '9%', className: 'bg-emerald-500' },
];

const employees = [
    { name: 'Ayu Prameswari', department: 'People Ops', status: 'Aktif', tone: 'emerald' },
    { name: 'Bima Saputra', department: 'Engineering', status: 'Aktif', tone: 'emerald' },
    { name: 'Citra Halim', department: 'Finance', status: 'Cuti', tone: 'amber' },
    { name: 'Dimas Nugroho', department: 'Marketing', status: 'Aktif', tone: 'emerald' },
];

const timeline: TimelineItem[] = [
    {
        label: 'Screening',
        time: '02 Nov 2026 · 09:14',
        state: 'done',
        note: 'Lolos seleksi berkas',
    },
    {
        label: 'Interview',
        time: '04 Nov 2026 · 13:30',
        state: 'current',
        note: 'HR interview · 45 menit',
    },
    {
        label: 'Penawaran kerja',
        time: 'Tahap berikutnya',
        state: 'pending',
        note: 'Penawaran disiapkan setelah evaluasi wawancara.',
    },
    {
        label: 'Bergabung dengan tim',
        time: 'Belum dijadwalkan',
        state: 'pending',
        note: 'Konfirmasi penerimaan dan persiapan orientasi.',
    },
];

export function PipelinePlate() {
    return (
        <figure data-od-id="pipeline-plate">
            <figcaption className="flex items-baseline justify-between gap-4 font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                <span>Pipeline rekrutmen</span>
                <span>Nov 2026</span>
            </figcaption>

            <div className="mt-6 flex items-baseline justify-between border-b border-slate-300 pb-4">
                <span className="font-mono text-[10px] tracking-[0.14em] text-slate-500 uppercase">
                    Total kandidat
                </span>
                <span className="font-display text-5xl leading-none tracking-tight text-slate-900 tabular-nums">
                    34
                </span>
            </div>

            <ul className="mt-6 space-y-4">
                {pipeline.map((segment) => (
                    <li key={segment.label}>
                        <div className="flex items-baseline justify-between gap-4 font-mono text-[10px] tracking-[0.14em] uppercase">
                            <span className="text-slate-600">{segment.label}</span>
                            <span className="text-slate-900 tabular-nums">{segment.value}</span>
                        </div>
                        <div className="mt-2 h-[3px] bg-slate-200">
                            <div
                                className={cn('h-full', segment.className)}
                                style={{ width: segment.width }}
                            />
                        </div>
                    </li>
                ))}
            </ul>

            <div className="mt-8 grid grid-cols-2 gap-6 border-t border-slate-300 pt-5">
                <div>
                    <p className="font-mono text-[9px] tracking-[0.14em] text-slate-500 uppercase">
                        Lowongan
                    </p>
                    <p className="mt-1 font-display text-2xl leading-none text-slate-900 tabular-nums">
                        6
                    </p>
                    <p className="mt-1 font-mono text-[9px] tracking-[0.14em] text-slate-500 uppercase">
                        dibuka
                    </p>
                </div>
                <div>
                    <p className="font-mono text-[9px] tracking-[0.14em] text-slate-500 uppercase">
                        Wawancara
                    </p>
                    <p className="mt-1 font-display text-2xl leading-none text-slate-900 tabular-nums">
                        5
                    </p>
                    <p className="mt-1 font-mono text-[9px] tracking-[0.14em] text-slate-500 uppercase">
                        minggu ini
                    </p>
                </div>
            </div>

            <figcaption className="mt-6 text-[11px] leading-5 text-slate-500">
                Gbr. 01 — Contoh pipeline. Data fiktif, bukan data nyata.
            </figcaption>
        </figure>
    );
}

export function EmployeeTable() {
    return (
        <figure data-od-id="specimen-table">
            <div className="flex items-baseline justify-between gap-4 border-t border-slate-900 pt-3 font-mono text-[10px] tracking-[0.18em] uppercase">
                <span>Tabel 01 — Pegawai aktif</span>
                <span className="text-slate-500">128 baris</span>
            </div>

            <table className="mt-5 w-full text-left">
                <thead>
                    <tr className="border-b border-slate-300 font-mono text-[9px] tracking-[0.14em] text-slate-500 uppercase">
                        <th scope="col" className="pb-2 font-medium">
                            Nama
                        </th>
                        <th scope="col" className="pb-2 font-medium">
                            Departemen
                        </th>
                        <th scope="col" className="pb-2 text-right font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                    {employees.map((employee) => (
                        <tr key={employee.name}>
                            <td className="py-3 pr-3 text-[13px] font-medium text-slate-900">
                                {employee.name}
                            </td>
                            <td className="py-3 pr-3 text-[13px] text-slate-500">
                                {employee.department}
                            </td>
                            <td className="py-3 text-right">
                                <StatusBadge
                                    variant={employee.tone === 'emerald' ? 'success' : 'pending'}
                                >
                                    {employee.status}
                                </StatusBadge>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <figcaption className="mt-4 text-[11px] leading-5 text-slate-500">
                Contoh tampilan. Data fiktif.
            </figcaption>
        </figure>
    );
}

export function EmployeeForm() {
    return (
        <figure data-od-id="specimen-form">
            <div className="flex items-baseline justify-between gap-4 border-t border-slate-900 pt-3 font-mono text-[10px] tracking-[0.18em] uppercase">
                <span>Formulir 02 — Pegawai</span>
                <span className="text-slate-500">Baru</span>
            </div>

            <div aria-hidden="true" className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span className="font-mono text-[9px] tracking-[0.12em] text-slate-500 uppercase">
                        Nama lengkap
                    </span>
                    <div className="mt-1.5 border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-900">
                        Ayu Prameswari
                    </div>
                </div>

                <div>
                    <span className="font-mono text-[9px] tracking-[0.12em] text-slate-500 uppercase">
                        Departemen
                    </span>
                    <div className="mt-1.5 flex items-center justify-between border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-900">
                        People Ops
                        <ChevronDown size={13} className="text-slate-400" />
                    </div>
                </div>

                <div>
                    <span className="font-mono text-[9px] tracking-[0.12em] text-slate-500 uppercase">
                        Jabatan
                    </span>
                    <div className="mt-1.5 border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-900">
                        HR Generalist
                    </div>
                </div>

                <div>
                    <span className="font-mono text-[9px] tracking-[0.12em] text-slate-500 uppercase">
                        Status
                    </span>
                    <div className="mt-2 flex items-center gap-5">
                        <span className="flex items-center gap-2 text-[12px] text-slate-900">
                            <span className="grid size-3 place-items-center border border-slate-900 bg-slate-900">
                                <span className="size-1 bg-white" />
                            </span>
                            Aktif
                        </span>
                        <span className="flex items-center gap-2 text-[12px] text-slate-500">
                            <span className="size-3 border border-slate-300" />
                            Cuti
                        </span>
                    </div>
                </div>

                <span className="inline-flex bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white">
                    Simpan pegawai
                </span>
            </div>

            <figcaption className="mt-4 text-[11px] leading-5 text-slate-500">
                Contoh tampilan. Data fiktif.
            </figcaption>
        </figure>
    );
}

export function ApplicationTimeline() {
    return (
        <figure data-od-id="specimen-timeline">
            <div className="flex items-baseline justify-between gap-4 border-t border-slate-900 pt-3 font-mono text-[10px] tracking-[0.18em] uppercase">
                <span>Riwayat 03 — Lamaran</span>
                <span className="text-slate-500">#A-014</span>
            </div>

            <div className="mt-6">
                <ProcessTimeline items={timeline} />
            </div>

            <figcaption className="mt-4 text-[11px] leading-5 text-slate-500">
                Contoh tampilan. Data fiktif.
            </figcaption>
        </figure>
    );
}
