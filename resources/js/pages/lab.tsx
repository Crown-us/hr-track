import { Head } from '@inertiajs/react';
import InkOrbitFeatures from '../components/ui/ink-orbit-features';

const notes = [
    {
        label: 'Yang dipakai',
        body: 'Tiga diagram SVG yang benar-benar bekerja: alur tim ke laporan, hub integrasi, dan grafik tren yang bisa digeser. Semua animasi berhenti saat prefers-reduced-motion aktif.',
    },
    {
        label: 'Yang belum sesuai',
        body: 'Gradient dua stop, shadow, sudut membulat, judul rata tengah, dan angka contoh "REPORT #155" melanggar aturan P0/P1 di DESIGN.md.',
    },
    {
        label: 'Keputusan',
        body: 'Komponen ditampilkan apa adanya di halaman lab, bukan di halaman awal. Bahasa visualnya belum boleh masuk alur produk sebelum disesuaikan.',
    },
];

export default function Lab() {
    return (
        <>
            <Head title="Lab komponen">
                <meta
                    name="description"
                    content="Lab komponen HRIS Lite — eksperimen komponen impor, bukan bagian alur produk."
                />
            </Head>

            <header data-od-id="lab-masthead" className="border-b border-slate-300">
                <div className="mx-auto flex max-w-[1280px] items-baseline justify-between gap-4 px-6 py-5 sm:px-10">
                    <a
                        href="/"
                        className="font-display text-[22px] leading-none tracking-tight text-slate-900"
                    >
                        HRIS Lite<span className="text-accent">.</span>
                    </a>
                    <p className="font-mono text-[10px] tracking-[0.18em] text-slate-500 uppercase">
                        Lab / Komponen
                    </p>
                </div>
            </header>

            <main className="mx-auto max-w-[1280px] px-6 sm:px-10">
                <section data-od-id="lab-intro" className="border-b border-slate-300 py-12">
                    <p className="font-mono text-[10px] tracking-[0.22em] text-slate-500 uppercase">
                        Berkas 02 — Eksperimen
                    </p>
                    <h1 className="mt-6 max-w-3xl font-display text-[clamp(2.2rem,4.5vw,3.6rem)] leading-[0.95] tracking-[-0.04em] text-balance text-slate-900">
                        Bento fitur, versi gelap.
                    </h1>
                    <p className="mt-6 max-w-2xl text-[15px] leading-7 text-pretty text-slate-600">
                        Komponen impor dari template SaaS. Ditampilkan apa adanya supaya bisa
                        dibandingkan dengan arah berkas/editorial, bukan untuk menggantikannya.
                        Halaman ini bukan bagian alur produk.
                    </p>

                    <dl className="mt-10 grid gap-8 border-t border-slate-300 pt-6 sm:grid-cols-3">
                        {notes.map((note) => (
                            <div key={note.label}>
                                <dt className="font-mono text-[10px] tracking-[0.18em] text-slate-900 uppercase">
                                    {note.label}
                                </dt>
                                <dd className="mt-2 text-[13px] leading-6 text-slate-600">
                                    {note.body}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section data-od-id="lab-specimen" className="py-12">
                    <div className="flex items-baseline justify-between border-b border-slate-900 pb-3 font-mono text-[10px] tracking-[0.18em] uppercase">
                        <span>Gbr. 02 — Bento fitur</span>
                        <span className="text-slate-500">Tema gelap</span>
                    </div>

                    <div className="mt-6 border border-slate-300">
                        <InkOrbitFeatures
                            theme="dark"
                            brand="HRIS Lite"
                            tag="Rancangan"
                            title={'Satu *alur*,\nsatu berkas.'}
                            collaboration={{
                                title: 'Kolaborasi berkas',
                                description:
                                    'Tim HR dan pewawancara membuka catatan yang sama. Perubahan jadwal dan status kandidat langsung terlihat.',
                            }}
                            reports={{
                                title: 'Rekap otomatis',
                                description:
                                    'Pipeline dan hasil wawancara disusun dari data yang sama, tanpa menyalin ulang ke dokumen lain.',
                            }}
                            integrations={{
                                title: 'Sumber tersambung',
                                description:
                                    'Database, PDF, email, dan CV privat dibaca dari satu tempat.',
                                tools: ['MySQL', 'PDF', 'Email', 'CV'],
                            }}
                            insights={{
                                title: 'Tren lamaran',
                                description:
                                    'Jumlah lamaran per pekan dipakai untuk memperkirakan beban wawancara berikutnya.',
                                values: [22, 26, 24, 31, 29, 36, 34, 41, 39, 47, 52, 58],
                                forecastFrom: 8,
                            }}
                        />
                    </div>

                    <p className="mt-4 text-[11px] leading-5 text-slate-500">
                        Contoh tampilan. Angka pada diagram fiktif, bukan data nyata.
                    </p>
                </section>

                <section data-od-id="lab-next" className="border-t border-slate-300 py-10">
                    <div className="flex flex-wrap items-baseline justify-between gap-4">
                        <p className="max-w-xl text-sm leading-7 text-slate-600">
                            Kalau komponen ini mau dipakai di produk, tiga hal harus dibereskan
                            dulu: token warna, tipografi display serif, dan label data contoh.
                        </p>
                        <a
                            href="/"
                            className="font-mono text-[10px] tracking-[0.18em] text-slate-900 uppercase underline decoration-slate-300 underline-offset-4 transition hover:decoration-slate-900"
                        >
                            Kembali ke berkas 01
                        </a>
                    </div>
                </section>
            </main>
        </>
    );
}
