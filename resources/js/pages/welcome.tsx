import { Head } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, ChevronDown, Check } from 'lucide-react';
import { useState } from 'react';
import { ApplicationTimeline, EmployeeTable } from '../components/specimens';
import InkOrbitFeatures from '../components/ui/ink-orbit-features';
import { StatusBadge, StatusPalette } from '../components/ui/status-badge';

const modules = [
    {
        name: 'Pegawai',
        title: 'Kenali tim Anda.',
        text: 'Direktori pegawai dengan departemen, jabatan, dan status kerja. Informasi dasar yang tertata menjadi titik awal administrasi sehari-hari.',
        items: ['Profil & kontak kerja', 'Departemen & jabatan', 'Status kepegawaian'],
    },
    {
        name: 'Rekrutmen',
        title: 'Ikuti setiap kandidat.',
        text: 'Lowongan, berkas lamaran, dan wawancara berada dalam satu perjalanan. Baca tahap terakhir sebelum menentukan tindak lanjut.',
        items: [
            'Lowongan & berkas CV',
            'Screening hingga penerimaan',
            'Jadwal & undangan wawancara',
        ],
    },
    {
        name: 'Pelatihan',
        title: 'Beri ruang bertumbuh.',
        text: 'Rencanakan jadwal dan materi belajar, lalu bandingkan hasil pre-test serta post-test untuk membaca perkembangan peserta.',
        items: ['Jadwal & materi PDF', 'Daftar peserta', 'Evaluasi pre-test & post-test'],
    },
    {
        name: 'Hubungan kerja',
        title: 'Jaga catatan kerja.',
        text: 'Susun masa berlaku kontrak dan dokumen hubungan kerja. Riwayat yang jelas membantu HR menyiapkan tindak lanjut pada waktunya.',
        items: ['Kontrak PKWT & PKWTT', 'Pengingat masa kontrak', 'Catatan pelanggaran & SP'],
    },
    {
        name: 'Laporan',
        title: 'Baca gambaran utuh.',
        text: 'Rangkum perjalanan kandidat, pelatihan, dan administrasi pegawai untuk mendukung evaluasi tim HR.',
        items: ['Rekap rekrutmen', 'Riwayat pelatihan', 'Ekspor Excel & PDF'],
    },
];
const workflow = [
    {
        title: 'Catat kebutuhannya.',
        text: 'Mulai dari data pegawai, kebutuhan posisi, atau rencana pelatihan. Setiap proses memiliki berkasnya sendiri.',
    },
    {
        title: 'Ikuti perjalanannya.',
        text: 'Status dan riwayat menyimpan konteks. Ketahui apa yang sudah selesai dan apa yang perlu dikerjakan berikutnya.',
    },
    {
        title: 'Siapkan tindak lanjut.',
        text: 'Gunakan jadwal dan catatan sebagai pegangan saat mengatur wawancara, evaluasi, atau pembaruan kontrak.',
    },
];

export default function Welcome() {
    const [selected, setSelected] = useState(1);
    const current = modules[selected];
    return (
        <div className="taste-page northstar-page">
            <Head title="HR yang lebih tertata">
                <meta
                    name="description"
                    content="HRIS Lite menghubungkan informasi pegawai, perjalanan kandidat, dan administrasi HR. Kenali fitur dan cara kerjanya."
                />
            </Head>
            <a href="#konten" className="taste-skip">
                Lewati ke konten
            </a>
            <div className="northstar-header">
                <header className="taste-nav taste-wrap" data-od-id="masthead">
                    <a href="/" className="taste-wordmark" aria-label="HRIS Lite, beranda">
                        HRIS<span>Lite</span>
                        <span className="taste-mark" aria-hidden="true">
                            ↗
                        </span>
                    </a>
                    <nav aria-label="Navigasi utama">
                        <a href="#fitur">Fitur</a>
                        <a href="#cara-kerja">Cara kerja</a>
                        <a href="#tampilan">Tampilan</a>
                    </nav>
                    <a href="#akses" className="taste-nav-access">
                        Akses akun <ArrowUpRight size={16} aria-hidden="true" />
                    </a>
                </header>
            </div>
            <main id="konten">
                <section className="taste-hero taste-wrap" data-od-id="hero">
                    <h1>
                        Kelola orangnya.
                        <br />
                        <span>Rapikan prosesnya.</span>
                    </h1>
                    <p>
                        Informasi pegawai, rekrutmen, dan administrasi HR dalam satu ruang kerja.
                        Ikuti setiap berkas, pahami setiap langkah.
                    </p>
                    <div className="northstar-actions">
                        <a className="taste-button" href="#fitur">
                            Jelajahi fitur <ArrowRight size={17} aria-hidden="true" />
                        </a>
                        <a className="northstar-secondary" href="#tampilan">
                            Lihat tampilan <ArrowUpRight size={17} aria-hidden="true" />
                        </a>
                    </div>
                </section>{' '}
                <section
                    className="taste-bento taste-wrap"
                    aria-label="Alur informasi HR"
                    data-od-id="animated-bento"
                >
                    <InkOrbitFeatures
                        theme="light"
                        className="hris-bento taste-ink"
                        brand="HRIS Lite"
                        titleAs="h2"
                        tag=""
                        title={'Setiap berkas punya alur.'}
                        collaboration={{
                            title: 'Satu catatan, satu konteks',
                            description:
                                'Informasi kandidat dan catatan tim mengikuti perjalanan berkas, dari awal seleksi hingga evaluasi.',
                        }}
                        reports={{
                            title: 'Rangkuman yang terhubung',
                            description:
                                'Status dan riwayat memberi gambaran proses tanpa perlu menyusun kembali setiap catatan.',
                        }}
                        integrations={{
                            title: 'Dokumen mengikuti proses',
                            description:
                                'CV, materi pelatihan, dan kontrak berada dalam konteks pekerjaan yang membutuhkannya.',
                            tools: ['CV', 'Materi', 'Kontrak', 'PDF'],
                        }}
                        insights={{
                            title: 'Baca perkembangannya',
                            description:
                                'Contoh visual ringkasan aktivitas. Angka dan proyeksi pada diagram ini bersifat ilustratif.',
                            values: [22, 26, 24, 31, 29, 36, 34, 41, 39, 47, 52, 58],
                            forecastFrom: 8,
                        }}
                    />
                    <p className="taste-caption">
                        Ilustrasi alur informasi. Fitur aplikasi sedang disiapkan.
                    </p>
                </section>
                <section id="fitur" className="taste-modules taste-wrap" data-od-id="features">
                    <div className="taste-section-intro">
                        <h2>
                            Satu ruang.
                            <br />
                            Banyak urusan.
                        </h2>
                        <p>
                            Dari mengenal orang hingga menjaga administrasi. Pilih kebutuhan HR
                            untuk melihat cakupan yang sedang disiapkan.
                        </p>
                    </div>
                    <div className="taste-module-layout">
                        <div className="taste-module-choices" aria-label="Pilih kebutuhan HR">
                            {modules.map((module, index) => (
                                <button
                                    key={module.name}
                                    type="button"
                                    aria-pressed={selected === index}
                                    aria-controls="module-detail"
                                    onClick={() => setSelected(index)}
                                    className={selected === index ? 'is-active' : ''}
                                >
                                    {module.name}
                                    <ArrowUpRight size={20} aria-hidden="true" />
                                </button>
                            ))}
                        </div>
                        <div
                            id="module-detail"
                            className="taste-module-detail"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <div key={current.name} className="taste-detail-enter">
                                <h3>{current.title}</h3>
                                <p>{current.text}</p>
                                <ul>
                                    {current.items.map((item) => (
                                        <li key={item}>
                                            <Check size={17} aria-hidden="true" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                                <div className="northstar-module-preview" data-ui="module-preview">
                                    <div className="northstar-preview-bar">
                                        <span>HRIS Lite / {current.name}</span>
                                        <span>Data contoh</span>
                                    </div>
                                    {selected === 1 ? (
                                        <div className="northstar-pipeline">
                                            {[
                                                {
                                                    name: 'Screening',
                                                    person: 'Nadia Putri',
                                                    role: 'People Operations',
                                                    tone: 'review' as const,
                                                },
                                                {
                                                    name: 'Interview',
                                                    person: 'Raka Pradana',
                                                    role: 'Frontend Developer',
                                                    tone: 'progress' as const,
                                                },
                                                {
                                                    name: 'Offering',
                                                    person: 'Sari Wulandari',
                                                    role: 'Finance Staff',
                                                    tone: 'pending' as const,
                                                },
                                            ].map((item) => (
                                                <div key={item.name}>
                                                    <p className="northstar-column-name">
                                                        {item.name}
                                                    </p>
                                                    <article>
                                                        <span
                                                            className="northstar-avatar"
                                                            aria-hidden="true"
                                                        >
                                                            {item.person
                                                                .split(' ')
                                                                .map((n) => n[0])
                                                                .join('')}
                                                        </span>
                                                        <h4>{item.person}</h4>
                                                        <p>{item.role}</p>
                                                        <StatusBadge variant={item.tone} />
                                                    </article>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="northstar-records">
                                            {(selected === 0
                                                ? [
                                                      [
                                                          'Ayu Prameswari',
                                                          'People Operations',
                                                          'Aktif',
                                                      ],
                                                      ['Bima Saputra', 'Engineering', 'Aktif'],
                                                      ['Citra Halim', 'Finance', 'Cuti'],
                                                  ]
                                                : selected === 2
                                                  ? [
                                                        [
                                                            'Orientasi pegawai',
                                                            '12 November · Ruang pelatihan',
                                                            'Terjadwal',
                                                        ],
                                                        [
                                                            'Komunikasi kerja',
                                                            '18 November · Online',
                                                            'Terjadwal',
                                                        ],
                                                        [
                                                            'Evaluasi pelatihan',
                                                            'Pre-test & post-test',
                                                            'Disiapkan',
                                                        ],
                                                    ]
                                                  : selected === 3
                                                    ? [
                                                          [
                                                              'Kontrak kerja',
                                                              'PKWT · Engineering',
                                                              'Ditinjau',
                                                          ],
                                                          [
                                                              'Pembaruan kontrak',
                                                              'Berakhir 28 November',
                                                              'Tindak lanjut',
                                                          ],
                                                          [
                                                              'Dokumen hubungan kerja',
                                                              'Arsip administrasi',
                                                              'Tersimpan',
                                                          ],
                                                      ]
                                                    : [
                                                          [
                                                              'Rekrutmen',
                                                              'Rekap kandidat per tahap',
                                                              'PDF',
                                                          ],
                                                          [
                                                              'Pelatihan',
                                                              'Peserta dan hasil evaluasi',
                                                              'Excel',
                                                          ],
                                                          [
                                                              'Administrasi pegawai',
                                                              'Ringkasan periode berjalan',
                                                              'PDF',
                                                          ],
                                                      ]
                                            ).map(([name, detail, status]) => (
                                                <div key={name}>
                                                    <div>
                                                        <strong>{name}</strong>
                                                        <p>{detail}</p>
                                                    </div>
                                                    <span>{status}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <a href="#tampilan">
                                    Lihat cuplikan lainnya{' '}
                                    <ArrowRight size={17} aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                    </div>
                    <noscript>
                        <p>
                            Aktifkan JavaScript untuk memilih cakupan fitur. HRIS Lite mencakup
                            pegawai, rekrutmen, pelatihan, hubungan kerja, dan laporan.
                        </p>
                    </noscript>
                </section>
                <section id="cara-kerja" className="taste-workflow" data-od-id="workflow">
                    <div className="taste-wrap taste-workflow-inner">
                        <h2>
                            Setiap langkah,
                            <br />
                            jelas arahnya.
                        </h2>
                        <div className="taste-workflow-list">
                            {workflow.map((step) => (
                                <article key={step.title}>
                                    <h3>{step.title}</h3>
                                    <p>{step.text}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
                <section
                    id="tampilan"
                    className="taste-preview taste-wrap"
                    data-od-id="product-preview"
                >
                    <div className="taste-section-intro">
                        <h2>
                            Kenali ruang
                            <br />
                            kerjanya.
                        </h2>
                        <p>
                            Tabel yang mudah dipindai, status yang mudah dikenali, dan riwayat yang
                            bisa diikuti. Seluruh informasi berikut adalah data contoh fiktif.
                        </p>
                    </div>
                    <div className="taste-preview-layout">
                        <div className="taste-directory" data-ui="employee-directory">
                            <EmployeeTable />
                        </div>
                        <div className="taste-history" data-ui="candidate-timeline">
                            <ApplicationTimeline />
                        </div>
                        <div className="taste-status" data-ui="process-status">
                            <h3>Setiap proses punya penanda.</h3>
                            <p>Status untuk lamaran dan dokumen.</p>
                            <StatusPalette />
                        </div>
                        <div className="taste-preview-note">
                            <p>
                                Sebuah nama di direktori.
                                <br />
                                Sebuah perjalanan di baliknya.
                            </p>
                            <a href="#cara-kerja">
                                Pahami cara kerjanya <ArrowRight size={18} aria-hidden="true" />
                            </a>
                        </div>
                    </div>
                </section>
                <section id="akses" className="taste-access" data-od-id="access">
                    <div className="taste-wrap taste-access-inner">
                        <div>
                            <h2>
                                Mulai dengan
                                <br />
                                akses yang tepat.
                            </h2>
                            <p>
                                Ruang kerja HRIS Lite ditujukan bagi tim yang memiliki akun dari
                                pengelola. Akses masuk sedang disiapkan.
                            </p>
                        </div>
                        <details>
                            <summary>
                                Bagaimana mendapatkan akses?{' '}
                                <ChevronDown size={20} aria-hidden="true" />
                            </summary>
                            <p>
                                Akun akan diberikan oleh pengelola saat aplikasi siap digunakan.
                                Pendaftaran mandiri belum tersedia. Anda tetap dapat menjelajahi
                                cakupan fitur dan contoh tampilan di halaman ini.
                            </p>
                        </details>
                    </div>
                </section>
            </main>
            <footer className="taste-footer taste-wrap" data-od-id="footer">
                <div className="taste-footer-top">
                    <p>
                        Informasi terhubung.
                        <br />
                        Tim bertumbuh.
                    </p>
                    <a href="#konten">Kembali ke atas ↑</a>
                </div>
                <a href="/" className="taste-footer-name" aria-label="HRIS Lite, beranda">
                    hris lite<span aria-hidden="true">↗</span>
                </a>
                <div className="taste-footer-bottom">
                    <p>Pengelolaan sumber daya manusia</p>
                    <p>Dirancang untuk pekerjaan HR sehari-hari.</p>
                </div>
            </footer>
        </div>
    );
}
