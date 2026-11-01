import { Head } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpRight } from 'lucide-react';
import {
    ApplicationTimeline,
    EmployeeForm,
    EmployeeTable,
    PipelinePlate,
} from '../components/specimens';

const indexItems = [
    {
        number: '01',
        title: 'Data pegawai',
        description: 'Departemen, jabatan, status, dan arsip dalam satu catatan.',
        metric: '128 aktif',
        href: '#tabel-pegawai',
    },
    {
        number: '02',
        title: 'Rekrutmen',
        description: 'Lowongan, kandidat, dan pipeline yang bisa diaudit.',
        metric: '6 dibuka',
        href: '#pipeline',
    },
    {
        number: '03',
        title: 'Wawancara',
        description: 'Jadwal, pewawancara, catatan, dan undangan PDF.',
        metric: '5 minggu ini',
        href: '#riwayat',
    },
    {
        number: '04',
        title: 'Akses',
        description: 'Admin, HR staff, viewer. Diperiksa di server, bukan di tombol.',
        metric: '3 peran',
        href: '#status',
    },
];

const completed = [
    'Laravel 13 + Inertia 3 + React 19',
    'MySQL development dan test terpisah',
    'Halaman awal dan quality gate',
    '5 tes / 18 assertions',
];

const next = [
    'Autentikasi dan role',
    'CRUD pegawai dan lowongan',
    'Pipeline kandidat (ATS)',
    'Wawancara, PDF, dan CV privat',
];

export default function Welcome() {
    return (
        <>
            <Head title="Selamat datang">
                <meta
                    name="description"
                    content="HRIS Lite — prototipe pengelolaan pegawai, rekrutmen, dan wawancara untuk portofolio independen."
                />
            </Head>

            <a
                href="#konten"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:bg-slate-900 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
            >
                Lewati ke konten
            </a>

            <header data-od-id="masthead" className="border-b border-slate-300">
                <div className="mx-auto flex max-w-[1280px] items-baseline justify-between gap-4 px-6 py-5 sm:px-10">
                    <a
                        href="/"
                        className="rounded-sm font-display text-[22px] leading-none tracking-tight text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900"
                    >
                        HRIS Lite<span className="text-accent">.</span>
                    </a>
                    <div className="flex items-baseline gap-5 font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                        <span className="hidden sm:inline">Berkas 01 / Prototipe / 2026</span>
                        <a
                            href="#indeks"
                            className="rounded-sm text-slate-900 underline decoration-slate-300 underline-offset-4 transition hover:decoration-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                        >
                            Indeks ↓
                        </a>
                    </div>
                </div>
            </header>

            <main id="konten">
                <section data-od-id="hero" className="mx-auto max-w-[1280px] px-6 sm:px-10">
                    <div className="grid border-b border-slate-300 py-14 lg:grid-cols-12 lg:gap-0 lg:py-20">
                        <div className="lg:col-span-7 lg:pr-12 xl:pr-20">
                            <p className="flex items-center gap-3 font-mono text-[10px] tracking-[0.22em] text-slate-500 uppercase">
                                <span aria-hidden="true" className="size-1.5 bg-emerald-500" />
                                Berkas 01 — Fondasi
                            </p>

                            <h1 className="mt-8 font-display text-[clamp(2.9rem,6vw,5.4rem)] leading-[0.92] tracking-[-0.045em] text-balance text-slate-900">
                                Setiap orang punya <em className="text-accent italic">berkas</em>.
                                <br />
                                Setiap berkas punya alur.
                            </h1>

                            <p className="mt-8 max-w-xl text-[15px] leading-7 text-pretty text-slate-600">
                                HRIS Lite menyatukan data pegawai, pipeline kandidat, dan jadwal
                                wawancara dalam satu catatan yang bisa ditelusuri. Dibangun sebagai
                                prototipe portofolio, bukan sistem production.
                            </p>

                            <div className="mt-10 flex flex-wrap items-center gap-x-8 gap-y-4">
                                <a
                                    href="#indeks"
                                    className="group inline-flex items-center gap-3 border-b border-slate-900 pb-1.5 text-sm font-semibold text-slate-900 transition hover:border-slate-950 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900 active:translate-y-px"
                                >
                                    Telusuri isi berkas
                                    <ArrowDown
                                        size={15}
                                        aria-hidden="true"
                                        className="transition group-hover:translate-y-0.5"
                                    />
                                </a>
                                <a
                                    href="#status"
                                    className="rounded-sm text-sm text-slate-600 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-900 hover:decoration-slate-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900"
                                >
                                    Status pengembangan
                                </a>
                            </div>

                            <p className="mt-10 font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                                Prototipe independen · Data fiktif · Bahasa Indonesia
                            </p>
                        </div>

                        <div
                            id="pipeline"
                            className="mt-12 scroll-mt-8 lg:col-span-5 lg:mt-0 lg:border-l lg:border-slate-300 lg:pl-12"
                        >
                            <PipelinePlate />
                        </div>
                    </div>
                </section>

                <section
                    id="indeks"
                    data-od-id="index"
                    className="mx-auto max-w-[1280px] scroll-mt-8 px-6 sm:px-10"
                >
                    <div className="flex items-baseline justify-between border-b border-slate-300 py-8">
                        <p className="font-mono text-[10px] tracking-[0.22em] text-slate-500 uppercase">
                            Indeks isi
                        </p>
                        <p className="font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                            04 bagian · angka contoh
                        </p>
                    </div>

                    <ol className="divide-y divide-slate-300 border-b border-slate-300">
                        {indexItems.map((item) => (
                            <li key={item.number}>
                                <a
                                    href={item.href}
                                    className="group grid grid-cols-[2.25rem_1fr] items-baseline gap-x-4 gap-y-2 py-6 transition-colors hover:bg-white focus-visible:bg-white focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-slate-900 active:translate-y-px sm:grid-cols-12 sm:gap-x-6 sm:px-2"
                                >
                                    <span className="font-display text-xl text-slate-500 tabular-nums sm:col-span-1">
                                        {item.number}
                                    </span>
                                    <span className="font-display text-xl tracking-tight text-slate-900 transition group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4 sm:col-span-4">
                                        {item.title}
                                    </span>
                                    <span className="col-span-2 text-sm leading-6 text-slate-600 sm:col-span-5">
                                        {item.description}
                                    </span>
                                    <span className="col-span-2 flex items-center gap-2 font-mono text-[10px] tracking-[0.14em] text-slate-500 uppercase sm:col-span-2 sm:justify-end">
                                        {item.metric}
                                        <ArrowUpRight
                                            size={12}
                                            aria-hidden="true"
                                            className="opacity-0 transition group-hover:opacity-100"
                                        />
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ol>
                </section>

                <section
                    id="spesimen"
                    data-od-id="specimens"
                    className="mx-auto max-w-[1280px] scroll-mt-8 px-6 sm:px-10"
                >
                    <div className="border-b border-slate-300 py-10">
                        <p className="font-mono text-[10px] tracking-[0.22em] text-slate-500 uppercase">
                            Spesimen antarmuka
                        </p>
                        <h2 className="mt-4 max-w-2xl font-display text-3xl tracking-tight text-balance text-slate-900 sm:text-4xl">
                            Potongan dari sistem yang sedang dibangun.
                        </h2>
                    </div>

                    <div className="grid gap-12 py-12 md:grid-cols-2 lg:grid-cols-12 lg:gap-10">
                        <div id="tabel-pegawai" className="scroll-mt-8 md:col-span-2 lg:col-span-6">
                            <EmployeeTable />
                        </div>
                        <div id="formulir" className="scroll-mt-8 lg:col-span-3">
                            <EmployeeForm />
                        </div>
                        <div id="riwayat" className="scroll-mt-8 lg:col-span-3">
                            <ApplicationTimeline />
                        </div>
                    </div>
                </section>

                <section
                    id="status"
                    data-od-id="status"
                    className="scroll-mt-8 border-y border-slate-300 bg-white"
                >
                    <div className="mx-auto max-w-[1280px] px-6 py-16 sm:px-10">
                        <div className="grid gap-12 lg:grid-cols-12">
                            <div className="lg:col-span-6">
                                <p className="font-mono text-[10px] tracking-[0.22em] text-slate-500 uppercase">
                                    Status berkas
                                </p>
                                <h2 className="mt-4 font-display text-4xl tracking-tight text-balance text-slate-900 sm:text-5xl">
                                    Fondasi selesai.
                                    <br />
                                    Isi menyusul.
                                </h2>
                                <p className="mt-6 max-w-lg text-sm leading-7 text-slate-600">
                                    Phase 1 menutup scaffold Laravel, database development dan test
                                    yang terpisah, halaman awal, serta quality gate. Phase 2–5
                                    mengisi autentikasi, role, data pegawai, ATS, wawancara, PDF,
                                    dan CV privat.
                                </p>
                                <p className="mt-8 inline-block -rotate-2 border-2 border-emerald-600 px-4 py-2 font-mono text-[10px] tracking-[0.2em] text-emerald-700 uppercase">
                                    Fase 01 · Selesai · 01 Nov 2026
                                </p>
                            </div>

                            <div className="grid gap-8 sm:grid-cols-2 lg:col-span-6">
                                <div>
                                    <h3 className="border-b border-slate-900 pb-2 font-mono text-[10px] tracking-[0.18em] text-slate-900 uppercase">
                                        Sudah ada
                                    </h3>
                                    <ul className="mt-4 space-y-3">
                                        {completed.map((item) => (
                                            <li
                                                key={item}
                                                className="flex gap-3 text-sm leading-6 text-slate-600"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className="mt-2 size-1.5 shrink-0 bg-emerald-500"
                                                />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <div>
                                    <h3 className="border-b border-slate-300 pb-2 font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                                        Berikutnya
                                    </h3>
                                    <ul className="mt-4 space-y-3">
                                        {next.map((item) => (
                                            <li
                                                key={item}
                                                className="flex gap-3 text-sm leading-6 text-slate-500"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className="mt-2 size-1.5 shrink-0 border border-slate-400"
                                                />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer data-od-id="colophon" className="mx-auto max-w-[1280px] px-6 sm:px-10">
                <div className="grid gap-8 border-b border-slate-300 py-10 sm:grid-cols-2">
                    <div>
                        <p className="max-w-xs text-sm leading-6 text-slate-600">
                            Prototipe portofolio independen. Tidak untuk production. Seluruh data
                            demo fiktif.
                        </p>
                        <a
                            href="#konten"
                            className="mt-4 inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.18em] text-slate-900 uppercase underline decoration-slate-300 underline-offset-4 transition hover:decoration-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                        >
                            Kembali ke atas
                            <ArrowUp size={12} aria-hidden="true" />
                        </a>
                    </div>
                    <div className="font-mono text-[10px] leading-5 tracking-[0.16em] text-slate-500 uppercase sm:text-right">
                        <p>Laravel 13 · Inertia 3 · React 19</p>
                        <p>MySQL 8.4 · PHP 8.3.36</p>
                        <p>2026</p>
                    </div>
                </div>

                <div aria-hidden="true" className="overflow-hidden pt-8 pb-4">
                    <p className="font-display text-[clamp(3.5rem,17vw,15rem)] leading-[0.75] tracking-[-0.06em] whitespace-nowrap text-slate-900">
                        HRIS Lite
                    </p>
                </div>
            </footer>
        </>
    );
}
