# Progress — docs/PLAN.md

## Phase 1 — 1 November 2026

- Pengguna menyetujui fondasi; bekerja langsung di checkout sesuai brief. Branch lokal feat/hris-foundation; tanpa remote/push.
- Composer 2.11.1 lokal dipasang setelah SHA384 installer diverifikasi. Network dan socket MySQL memerlukan eksekusi di luar sandbox.
- Skeleton Laravel v13.11.0 diunduh tanpa scripts/install ke staging .scaffold, manifest ditinjau lalu disalin tanpa menimpa docs.
- Ruling: PHPUnit 11.5 menggantikan default 12 agar kompatibel PHP 8.3.36. Tidak upgrade PHP major. Pail/Pao default tidak diperlukan.
- Ruling: AGENTS/CLAUDE bootstrap skeleton diganti catatan proyek. Boost tambahan tidak diperlukan scope dependency minimal pengguna; Composer tersedia lokal, bukan PATH global.
- MySQL 8.4.3 ditemukan di Laragon. Instance menggunakan .local/mysql-data pada 127.0.0.1:3307, tanpa menyentuh data Laragon lama. Root dan dua akun memakai password acak lokal; privilege hris_dev/hris_test hanya pada database masing-masing.
- Ruling: binary MySQL tersedia dipakai untuk demo loopback; bukan klaim patch terbaru. Update MySQL perlu sebelum production. Tidak memasang service/autostart atau mengaktifkan Docker.
- Development/test menerima tiga migration bawaan (user/session, cache, jobs); domain HR mulai Phase 2.
- RED: FoundationTest gagal "Not a valid Inertia response" sebelum route React. GREEN setelah middleware, Blade root dan welcome dibuat.
- Tes isolasi membuktikan hris_test tidak dapat SELECT hris_lite. Semua tes database memakai MySQL.
- Full suite awal gagal ExampleTest karena build belum ada; setelah Vite build seluruh 5 tes/18 assertions lolos.
- TypeScript, ESLint dan build lolos. Pint memperbaiki import pada dua file. Pemeriksaan akhir menyusul review.
- Belum ada login, akun demo, CRUD domain atau dashboard privat. Welcome menjelaskan tahap pengembangan tanpa angka palsu.

Berikutnya: Phase 2 — Fortify auth, role/policy, migration domain, factory dan seeder fiktif.

## Review dan perbaikan akhir

Review independen tidak menemukan defect penting pada scope Phase 1, dengan dua saran hardening. Keduanya ditangani karena berkaitan dengan setup dan isolasi database yang dijanjikan:
- DB_URL warisan terminal: regression menjalankan suite dengan URL kredensial invalid ke development awalnya menghasilkan dua kegagalan. Force ENV saja belum cukup pada PHP Windows karena SERVER juga dipakai. Setelah force pada keduanya, 5 tes/18 assertions lolos dengan URL invalid tersebut tetap terpasang. Database/user/driver test juga dipaksa pada SERVER.
- Skrip start MySQL sekarang menunggu listener localhost maksimum 30 detik, gagal eksplisit jika startup gagal, sehingga command berikut tidak berlomba dengan startup.
- npm audit awal menemukan 12 laporan moderate yang bersumber dari minimatch 10 transitif dev tooling. Override minimatch ^11.0.1 dipasang setelah memeriksa dukungan Node dan ekspor CommonJS/ESM; audit setelah install: 0 vulnerability. Evaluasi pencabutan override setelah dependency upstream memakai patch.
- Composer audit melaporkan tidak ada advisory, tetapi memperingatkan timeout repo.packagist.org dan fallback cache; ini bukan verifikasi advisory online penuh.
- Browser: halaman React berhasil dirender, tautan anchor berfungsi, viewport 375px tidak overflow horizontal, console warn/error kosong. Tidak mengklaim seluruh UI MVP sudah tersedia.

Phase 1 complete: final gate 5 tests / 18 assertions PASS; Pint PASS; ESLint PASS; Prettier PASS; TypeScript PASS; Vite build PASS; npm audit 0 vulnerabilities; Composer validate/platform requirements PASS; HTTP preview 200. Cold restart MySQL dan migrate:status PASS. Tidak ada commit, deploy, email atau push. File rahasia terverifikasi ignored. Keputusan dan keterbatasan dicatat di atas.

## Penyempurnaan desain halaman awal — 1 November 2026

- Iterasi pertama (landing product-first dengan mock dashboard dalam frame browser) dinilai pengguna masih generik. Iterasi kedua mengganti konsep ke arah berkas/editorial, bukan lagi landing SaaS.
- `resources/js/pages/welcome.tsx` ditulis ulang: masthead minimal, headline serif besar, indeks isi empat baris, tiga spesimen antarmuka (tabel pegawai, formulir, riwayat lamaran), status Phase 1 dengan cap, dan colophon dengan wordmark besar.
- `resources/js/components/specimens.tsx` baru berisi `PipelinePlate`, `EmployeeTable`, `EmployeeForm` dan `ApplicationTimeline`; `demo-dashboard.tsx` dihapus. Palet brief (#F8FAFC/#0F172A/#4F46E5/#10B981) dipertahankan, tanpa dependency atau font eksternal.
- Spesimen diberi label "Contoh tampilan. Data fiktif."; formulir tiruan `aria-hidden` agar tidak disalahartikan sebagai kontrol aktif. Status tidak hanya dibedakan warna.
- Verifikasi aktual: viewport 375px (iframe) tanpa overflow horizontal; Lighthouse accessibility/best-practices/SEO 100; console tanpa warning/error; npm lint, format:check, types:check dan build lolos; 5 tes/18 assertions lolos; Pint lolos.
- Login, akun demo dan fitur operasional tetap belum tersedia.

## Anti-AI-slop pass — 1 November 2026

- Pengguna menetapkan aturan anti-AI-slop. Indigo default `#4F46E5` dari brief diganti vermillion `#B42318` karena termasuk daftar aksen terlarang; token dan alasannya dicatat di `DESIGN.md`.
- Semua kelas `indigo-*` di halaman dan spesimen dihapus. Pipeline memakai skala slate + emerald; dot status "current" memakai aksen. Aksen kini muncul pada kata italic `h1`, titik wordmark, dan dot status.
- Ditambahkan token `:root` (`--paper`, `--ink`, `--accent`, `--ok`, `--muted`, `--line`), tema Tailwind, serta `data-od-id` pada masthead, hero, indeks, spesimen, status, colophon dan tiap figure.
- Metrik contoh dilabeli "angka contoh" pada header indeks dan "Contoh tampilan. Data fiktif." pada spesimen. Tidak ada gradient, emoji, kartu rounded, shadow, blob, gambar eksternal atau copy pengisi.
- Verifikasi: 375px tanpa overflow; Lighthouse accessibility/best-practices/SEO 100; console bersih; Pint, ESLint, Prettier, TypeScript dan build lolos; 5 tes/18 assertions lolos.

## Komponen impor: bento fitur — 1 November 2026

- Pengguna meminta komponen `ink-orbit-features` (bento fitur tiga diagram) dipasang. Komponen disalin ke `resources/js/components/ui/ink-orbit-features.tsx` — folder `components/ui` dibuat karena proyek belum punya; semua impor memakai path relatif sebab `tsconfig.json` tidak mendefinisikan alias `@/`.
- Komponen tidak bergantung pada paket baru; React saja. Font Manrope/JetBrains Mono tidak diunduh, jatuh ke fallback sistem.
- Perbaikan saat integrasi: literal regex `parseTitle` yang rusak diganti `/\n|\\n/`; `"use client"` dibuang karena bukan Next.js; state tema dan reduced-motion diganti `useSyncExternalStore` agar lolos aturan `react-hooks/set-state-in-effect`; `useInView` diberi fallback 2 detik agar section tidak tinggal transparan bila IntersectionObserver tidak pernah terpanggil.
- Halaman `resources/js/pages/lab.tsx` + route `/lab` menampilkan komponen dengan tema gelap, copy Indonesia, dan label "angka fiktif". Komponen **tidak** dipasang di halaman awal: gradient, shadow, sudut membulat, judul rata tengah, dan `REPORT #155` melanggar P0/P1 `DESIGN.md`.
- Tautan "Lab komponen" ditambahkan di colophon halaman awal.
- Verifikasi: `npm run lint`, `format:check`, `types:check`, `build` lolos; Lighthouse /lab 100 accessibility/best-practices/SEO; console bersih; viewport 375px tanpa overflow; 6 tes/29 assertions dan Pint lolos.


## Referensi pengguna — light bento, timeline, status badge

- Bento InkOrbit homepage dipertahankan lengkap; hanya theme light dan penyesuaian token/font scoped. File animasi asli tidak diubah pada iterasi ini.
- ProcessTimeline reusable menerapkan garis vertikal, check selesai, clock aktif, tahap mendatang netral sesuai screenshot; data kandidat tetap fiktif.
- StatusBadge reusable menyediakan tujuh varian pastel berikon. Digunakan pada tabel pegawai dan palet status proses.
- Layout spesimen menjadi tabel 7 kolom / timeline 5 kolom, formulir satu baris terpisah pada desktop; susun vertikal mobile. DESIGN.md diperbarui dengan pengecualian eksplisit sesuai instruksi pengguna terbaru.
- Verifikasi: ESLint, TypeScript dan build Vite PASS. Kontrol browser gagal dengan error konektor, sehingga tidak mengklaim visual terbaru sudah diverifikasi di browser.
- Backend, route dan data database tidak diubah pada iterasi ini. Perubahan pengguna yang sudah ada dipertahankan.

Landing dirombak menjadi pengenalan produk: navigasi, bento light utuh, enam cakupan fitur, tiga langkah cara kerja, cuplikan tabel/timeline/badge, akses akun dan footer. Narasi portofolio/progres/stack dihapus dari UI. Lint, format, type-check, Vite build PASS; FoundationTest 2 tests/22 assertions PASS. Browser berhasil merender landing terbaru; screenshot lokal .local/landing-redesign.png. Login masih belum tersedia dan tombol diberi penjelasan eksplisit.


## Taste Skill pass

Landing dimatangkan mengikuti editorial-report / The printed annual, seed 1718636150. Judul desktop 120px (sebelumnya 44px), mobile 50px (sebelumnya 28px), body 17px (sebelumnya 14px). Pemilih fitur interaktif, field cara kerja, layout preview adaptif dan footer baru. Bento/timeline/badge tetap ada. Inspector awal 13 FAIL; final 0 FAIL/4 WARN dengan pengecualian icon-cards untuk bento yang wajib dipertahankan. Source lint Taste 0 FAIL/0 WARN. Build/lint/types/format PASS; 2 tests/22 assertions PASS; browser click + keyboard, anchor dan console terverifikasi. Kritik jujur dan batasan ada di docs/TASTE-REVIEW.md. Tidak ada deployment/push atau perubahan database.

## Northstar reference pass

Atas persetujuan pengguna, komposisi landing mengikuti referensi Northstar: hero centre sans-serif, aksen biru tua, navigasi dengan wordmark tengah di desktop, dua CTA, menu fitur kiri + detail dan contoh isi kanan. Setiap modul memperbarui preview fiktifnya; pipeline default tiga kandidat, modul training menampilkan tiga agenda/evaluasi. Bento animasi, timeline dan badge tetap ada, backend tidak berubah. Tidak mengambil kode/aset dari referensi.

Verifikasi aktual: ESLint, TypeScript, Prettier, Vite build PASS; FoundationTest 2 tests/22 assertions PASS. Screenshot 1440/768/390 dibuka seluruhnya. Headline masing-masing dua baris, tanpa laporan overflow/clipping. Klik Pelatihan mengganti detail dan preview; console bersih.

Inspector Taste mentah: 5 FAIL / 6 WARN. Empat FAIL merupakan larangan gaya (shadow, hairline, outlined-box, icon-cards) yang sengaja dikesampingkan sesuai referensi dan komponen wajib pengguna; small-text menandai metadata 12px, tidak diklaim lolos house style Taste. Batasan tetap: auth belum tersedia, data contoh fiktif, sebagian microcopy diagram asli masih Inggris. Screenshot di .taste-skill/northstar. Bukan klaim 0 FAIL seperti desain sebelumnya karena preferensi visual sekarang berbeda.

## Font pairing — 2 November 2026

Space Grotesk + DM Sans dipasang lokal dari Google Fonts, dengan lisensi OFL dari repository google/fonts. Stylesheet lokal public/fonts/fonts.css dan preload DM Sans400/SpaceGrotesk600 pada root Blade. Build PASS. Browser memverifikasi kedua font loaded=true serta computed font headline/body benar. Mobile 390px: headline 75.59px / line-height37.8px = 2 baris, documentWidth375 <= viewport390. Screenshot .local/font-preview.png. Layout serta komponen pilihan pengguna dipertahankan.

## Tipografi netral ala Apple — 2 November 2026

Native Apple system font + Inter lokal pada Windows/Android, satu keluarga untuk heading dan body. SF Pro tidak disalin/self-host. Font Inter400/500/600/700 dari Google Fonts, lisensi OFL disertakan. Root Blade memuat inter.css dan preload400/600; theme-color putih. Build Vite dan Prettier check PASS. Browser Inter loaded=true. Mobile390: headline72.44px / line-height36.22px =2 baris; documentWidth375 <=390. Screenshot .local/neutral-type-preview.png. Tidak ada perubahan backend.

## Checkpoint landing — 2 November 2026

Bahasa bento, tooltip dan label aksesibilitas dirapikan ke Indonesia: rekap, sinkron, proyeksi, dokumen dan nama fiktif. Semua animasi serta komponen pilihan pengguna dipertahankan. Header sticky dan offset anchor telah dicek; screenshot mobile final390 dibuka, headline2 baris dan tidak ada laporan overflow. Peringatan inspector tetap terkait style pilihan pengguna dan metadata12px; bukan klaim lolos house style Taste.

Quality gate: Pint PASS; seluruh suite6 tests/29 assertions PASS; ESLint, TypeScript, Prettier dan Vite build PASS. Git ignore memastikan .env/.env.testing/.local/.tools tidak masuk checkpoint. Push diminta pengguna, target origin/feat/hris-foundation pada github.com/Crown-us/hr-track; tanpa force-push, merge atau deployment. Login/domain backend tetap pekerjaan berikutnya.
