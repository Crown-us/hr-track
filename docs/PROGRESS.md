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

## Login dan aktivasi akun — 2 November 2026

Fortify 1.41.0 dipasang pada Laravel 13; registrasi bawaan/fitur email/reset/2FA dinonaktifkan. Login/logout memakai session web, regenerasi session saat masuk, invalidasi saat keluar, CSRF dan pembatasan request (5/email+IP dan 20/IP per menit; signup 10/IP). Halaman React `/login` dan `/register` mengikuti palet/font ruang HR, dengan error Indonesia, tampil/sembunyikan password, remember-me dan loading. Landing mengarah ke login. `/hr` memakai auth + gate role admin/hr_staff/viewer, identitas asli dan tombol logout; seluruh data bisnis tetap contoh.

Migration additive menambah users.role default viewer dan account_invitations. Signup hanya dari undangan dengan token acak 64 karakter, hash SHA-256 di database, kedaluwarsa 48 jam, row lock dan used_at untuk sekali pakai. Email/role berasal dari undangan, bukan payload browser. Password minimal12, huruf besar/kecil dan angka, batas72 byte dan penolakan null agar bcrypt tidak memotong input. `/register` tanpa token hanya menjelaskan akses; invalid/expired/used token tidak membuka form. Header no-store dan no-referrer pada halaman akses/HR. CLI hr:create-admin (input password tersembunyi, hanya admin pertama) dan hr:invite; tidak ada kredensial bawaan atau email otomatis. Panduan di README dan AUTH-PLAN.

Bukti: RED awal route404/schema belum ada, lalu GREEN. Regresi batas byte password sempat RED lalu diperbaiki. Review independen menemukan secure cookie harus mengikuti fallback production ketika APP_ENV hilang; diperbaiki dan regression test ditambahkan (test sempat perlu koreksi karena repository environment immutable tidak dapat menghapus variabel PHPUnit). Final php artisan test: **21 tests/153 assertions PASS**, Pint PASS, ESLint/Prettier/TypeScript/Vite build PASS, composer validate PASS. Composer install melaporkan tidak ada advisory. Sandbox memerlukan eksekusi dengan izin socket/file yang sesuai; validasi aplikasi tidak dilemahkan. Migration development hanya additive, tanpa reset. Test menggunakan database terpisah.

Browser memverifikasi error login salah, perpindahan tab signup, tampilan desktop dan390px, signup valid melalui undangan QA fiktif (tanpa membuat akun), dan console kosong. Mobile documentWidth390 sama dengan viewport390. Screenshot .local/auth-login.png, auth-signup.png dan auth-signup-mobile.png. Undangan QA dan file tautannya dihapus setelah pengecekan. Tidak ada default akun dibuat, deploy, commit atau push. Batas: CRUD HR, pengelolaan akun via UI, email/reset password/verifikasi/2FA belum tersedia; scope ini belum menyelesaikan seluruh Phase2.

## Pegawai dari database dan arsip terkendali — 2 November 2026

Ikon « dan breadcrumb Ruang kerja di header dihapus. Shell responsif menjadi komponen bersama. `/employees` menyediakan tambah/detail/edit, pencarian nama/nomor/email, filter departemen/status, sort whitelist dan pagination15. Dashboard `/hr` memakai count/cuplikan pegawai aktual; rekrutmen tetap fiktif dengan label eksplisit. `/preview/hr` tetap spesimen lokal. Master idempoten5 departemen/10 jabatan sudah diisi tanpa membuat pegawai bisnis atau mengubah akun pengguna.

Migration additive departments/positions/employees/employee_events, enum status, FK gabungan jabatan-departemen, unique nomor/email termasuk arsip, dan versi integer. Policy: admin/hr_staff CRU; viewer baca; hanya Admin arsip/restore/berkas. Arsip wajib alasan20–1000 karakter, PDF<=5MiB (ekstensi/MIME/header diperiksa), konfirmasi nomor dan checkbox pemeriksaan. Berkas di disk employee_records privat serve=false; download attachment/no-store/nosniff setelah otorisasi dan pemeriksaan kepemilikan event. Actor audit dari session. Row lock+version menolak formulir lama. File dibersihkan jika transaksi gagal. Restore tetap menyimpan seluruh riwayat. Tidak ada route hapus permanen.

Workflow: pegawai resign dapat ditandai Nonaktif; arsip cocok untuk catatan duplikat/salah input. Ini keputusan aplikasi sesuai permintaan pengguna; retensi dan pemusnahan data tidak ditebak sebagai aturan hukum. Belum ada approval dua orang, UI master, antivirus/sanitasi PDF atau dokumen personal umum. Scope dan keputusan di EMPLOYEE-PLAN.md; README/ERD diperbarui.

Bukti RED: route/model belum tersedia; setelah implementasi ditemukan default version model belum tersinkron dengan DB dan fixture PDF palsu dapat lolos MIME yang dilaporkan fake uploader; ditangani default attribute dan cek signature PDF. GREEN final **34 tests/257 assertions PASS**, Pint PASS, lint/format/types/build PASS. Tes mencakup otorisasi, CRU, unique/mismatch, FK langsung, stale update/arsip, pengarsipan/restore, file privat lintas role/pegawai, file hilang/palsu/besar dan rollback cleanup. Review independen menemukan serialisasi boolean Inertia pada query archived; frontend sekarang mengirim0/1 dan pencarian/filter benar-benar diuji di browser setelah perbaikan.

Browser: tambah pegawai QA sementara, edit nama tersimpan dan audit muncul, cari nama hasil edit menghasilkan1, filter Finance menghasilkan0, tombol arsip tanpa syarat lengkap disabled. Form arsip desktop/mobile390 diperiksa; documentWidth375<=390; console kosong. Screenshot .local/employee-archive.png, employee-archive-mobile.png, employees-ready.png. Pegawai QA dan audit uji dibersihkan dengan pengecekan nomor/email/nama persis; tidak ada data pengguna lain dihapus. Akun Admin HR tetap tersedia. Migration development tidak di-reset. Tidak commit/push/deploy.

## Pratinjau ruang kerja HR — 2 November 2026

Route lokal `/preview/hr` menampilkan shell HR dengan sidebar netral dan kepadatan seperti referensi pertama pengguna. Tiga tampilan: ringkasan HR, direktori pegawai dan rekrutmen board/daftar. Data terpusat di resources/js/data/hr-preview.ts, seluruhnya fiktif: 8 pegawai, 3 lowongan, 8 kandidat dengan distribusi 3 screening/2 interview/1 offering/1 diterima/1 ditolak, 2 wawancara contoh pada 2 November 2026. Semua total dihitung dari dataset, bukan KPI karangan.

## Lowongan internal — 2 November 2026

Menu Rekrutmen sekarang membuka `/vacancies`, tab Lowongan. Tab Pipeline menuju pratinjau kandidat berlabel Contoh. Admin/Staf HR dapat tambah/detail/edit/status; Viewer baca. Isian: judul, departemen, jenis kerja, lokasi, deskripsi, persyaratan, tanggal buka/tutup, draft/dibuka/ditutup. Pencarian judul/lokasi, filter departemen/status, sort whitelist dan pagination15. Migration additive000003 memiliki FK departemen, CHECK tanggal/status/jenis kerja, soft-delete untuk fondasi dan versi integer. Update memakai row lock+version untuk menolak form lama. Tidak ada route hapus lowongan.

Dashboard memakai count/cuplikan lowongan nyata, published dalam rentang tanggal WIB inklusif; lowongan terjadwal/berakhir tidak dihitung aktif. Klik lowongan aktual membuka detail, bukan kandidat contoh. Lowongan belum muncul di internet/halaman karier, tidak ada pelamar/CV tersimpan pada fase ini. Status Ditutup mempertahankan catatan. Scope di VACANCY-PLAN.md; README/ERD/PLAN diperbarui.

Bukti RED6 tes sebelum route/model tersedia, GREEN setelah backend. Final **40 tests/326 assertions PASS**, Pint PASS; format/lint/types/build PASS. Review independen tidak menemukan defect penting pada scope. React key dashboard memakai ID agar judul posisi yang sama tidak menyebabkan duplikasi key. Search mengikutkan nilai input saat filter dipilih agar kata kunci tidak hilang bila navigasi sebelumnya masih berjalan.

Browser: draft QA disimpan, detail terbuka, status diedit menjadi Ditutup tanpa menghapus record; search kata tidak cocok→0 dan QA-LOWONGAN→1; filter Draft→0; perpindahan Lowongan/Pipeline berhasil. Detail desktop dan mobile390 diperiksa (documentWidth375<=390), console kosong. Screenshot .local/vacancies-ready.png, vacancy-detail.png, vacancy-mobile.png. Record QA sementara dibersihkan dengan pemeriksaan judul/lokasi/deskripsi/status/version persis; akun dan data lain dipertahankan. Tidak reset development, commit, push atau deploy.

Pencarian, filter departemen/lowongan, board/daftar, dialog detail/timeline dan sidebar mobile interaktif. Tidak ada CRUD/persistensi, status mutation, unggah CV atau auth production. Akun pada sidebar hanya ilustrasi. Route mengembalikan404 selain environment local/testing, tidak mengirim props database. Landing tetap terpisah. Pelatihan/hubungan kerja/laporan diberi label Segera.

Verifikasi: RED test pratinjau404 sebelum route dibuat; GREEN setelah implementasi. Seluruh8 tes/41 assertions PASS, Pint PASS; ESLint, TypeScript dan build PASS. Awal full suite gagal dua tes koneksi karena MySQL belum berjalan; server proyek dinyalakan lewat skrip dan full suite diulang sampai PASS. Browser: query Raka→1 kandidat; filter Engineering→2 pegawai; dialog dan Escape; board/daftar; menu mobile buka/tutup; mobile390 documentWidth375 (tidak overflow), console kosong. Screenshot .local/hr-dashboard.png. Dataset tidak dimutasi dan tidak menyentuh database development.

## Pelamar, lamaran dan CV privat — 2 November 2026

Input internal HR tersedia di `/applications/create`: profil baru atau profil terdaftar melalui pencarian20 hasil, lowongan aktif, tanggal dalam periode (bukan masa depan), CV PDF opsional dan catatan HR. Identitas terpisah dari aplikasi; email unik lowercase, pasangan pelamar/lowongan unik. Profil lama tidak ditimpa. Migration000004 additive dan FK RESTRICT; tanpa reset DB development.

`/applications` menampilkan board/daftar, search/filter dan pagination15. Jumlah tahap seluruh hasil filter; kartu board halaman aktif. Detail memakai timeline asli dan status badge pilihan pengguna. Screening awal dan transisi legal terkunci: Screening→Interview/Ditolak; Interview→Offering/Ditolak; Offering→Diterima/Ditolak; terminal. History append-only menyimpan actor session/waktu/catatan. Row lock+version menolak form lama.

CV berversi per profil dibagikan lintas lamarannya, disk applicant_documents privat serve=false. Batas5MiB, MIME/ekstensi/header PDF diperiksa, unduh attachment/no-store/nosniff dengan cek kepemilikan. Viewer tidak mendapat CV, telepon, catatan HR atau catatan history privat. Tes rollback DB membuktikan file tidak tertinggal dan history/status tidak terpisah. Belum antivirus/sanitasi PDF, edit/hapus profil/dokumen, jadwal/undangan interview atau portal publik.

Dashboard private memakai angka aktual untuk pegawai/lowongan/pelamar/lamaran/tahap interview, tanpa jadwal atau kandidat fiktif. Demo lokal tetap terpisah; legacy /hr?view=recruitment dialihkan ke pipeline aktual. Scope di APPLICATION-PLAN.md.

Verifikasi: RED8 tes sebelum endpoint/model, GREEN. Tambah regresi rollback file/history. Final **49 tests/422 assertions PASS**, Pint PASS; lint/format/types/build PASS. Type-check sempat mendeteksi transform() Inertia3 mengembalikan void; diperbaiki menjadi transform lalu post terpisah. Review independen tidak menemukan defect penting pada scope.

Browser: pelamar QA dibuat dengan CV PDF melalui form, detail/timeline/CV tampil, dua perubahan berurutan Screening→Interview→Offering berhasil (memastikan versi form diperbarui), board→daftar menampilkan data aktual. Detail desktop/mobile390 diperiksa, documentWidth375<=390, console kosong. Semua fixture QA (profil/aplikasi/history/CV/lowongan) dan file uji dibersihkan dengan pemeriksaan identitas/relasi/versi persis; akun/data lain tetap ada. Dashboard setelah cleanup benar-benar0, bukan angka contoh. Screenshot .local/application-detail.png, application-mobile.png, applications-ready.png. Tidak commit/push/deploy.
