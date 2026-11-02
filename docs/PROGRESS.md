# Progress — docs/PLAN.md

## Penyederhanaan bahasa antarmuka — 2 November 2026

Sesuai permintaan pengguna, label fiktif/demonstrasi dan disclaimer berulang di landing, pratinjau HR, spesimen, deskripsi bento, halaman Offering serta template PDF undangan/ringkasan diganti kalimat biasa yang menjelaskan fungsi. Tidak mengubah data pengguna, fixture tes, status fitur atau alur bisnis. Pengguna akan menyiapkan disclaimer terpisah. Penyebutan historis pada dokumentasi audit tetap dipertahankan sebagai catatan pekerjaan sebelumnya.

## Offering, respons dan konversi pegawai — 2 November 2026

Migration additive000008: recruitment_offers, recruitment_offer_events snapshot audit dan relasi unique employees.applicant_id/source_application_id dengan FK RESTRICT. Penawaran bisa direvisi sebelum respons; application.version+row lock melindungi update. Respons menerima/menolak/ditarik HR wajib tanggal, catatan dan bukti PDF5MiB privat (employee_records/offer-responses), mengubah tahap/history secara atomik. Respons sebelum revisi terakhir atau penerimaan lewat deadline ditolak. Diterima tidak dapat dipilih lewat endpoint tahap lama tanpa penawaran diterima; pending offer harus diputuskan melalui respons. Konversi memerlukan penawaran/lamaran diterima dan konfirmasi identitas, prefill nama/telepon, nomor/email kerja baru, penempatan/tanggal dari penawaran. EmployeeRecords audit dipakai kembali; profil/akun login tidak ditimpa/dibuat. Lock profil dan unique DB mencegah ganda lintas lamaran termasuk pegawai arsip. Kegagalan audit membatalkan status/file atau pegawai+auditnya. Ringkasan PDF berlabel internal/demonstrasi, bukan surat resmi/kontrak; Dompdf remote/PHP/JS off. Viewer/guest ditolak untuk penawaran/bukti/PDF/konversi.

RED4tes saat endpoint belum ada; GREEN setelah backend. Tambahan tes rollback konversi, duplikasi lintas lamaran/arsip dan tanggal revisi menemukan perlunya memperbarui timestamp pada save revisi tanpa perubahan isi; diperbaiki. Review independen menemukan form Inertia mempertahankan isian lama sambil mengirim versi props baru setelah konflik. Diperbaiki dengan versi terikat snapshot serta remount Terms/Response/Conversion pada application.version dan alert konflik. Browser dua tab membuktikan submit lama ditolak, Terms dimuat ulang ke revisi tab B dan catatan respons lama menjadi kosong; bukti input lama tidak dipertahankan.

Final **75 tests/1004 assertions PASS**, Pint PASS; lint/format/types/build PASS setelah koreksi import formatter tanggal. Journey publik kedua hasil akhir sekarang mencatat penawaran/respons; cabang diterima lanjut sampai pegawai. Browser di server sementara 127.0.0.2:8001 dengan database test, akun/kandidat fiktif, sesi database override hanya proses: revisi dua tab, form accepted, konversi melalui tombol dan detail pegawai berhasil, tautan kembali ke pegawai tampil. Pengunggahan bukti diuji HTTP/service, bukan browser file chooser. Mobile390 via CDP sementara: documentWidth375, screenshot diperiksa dan override dibersihkan. Console kosong. Semua fixture/audit/file fiktif dibersihkan dengan penjagaan identitas pada test DB; server uji dihentikan, data development tidak direset atau diisi QA. Migration development additive berhasil. Screenshot `.local/offering-conversion.png`, `.local/offering-mobile.png`; contoh PDF `.local/offering-summary-example.pdf` dirender Poppler dan diperiksa satu halaman A4. Belum formal offering/e-sign, email penawaran/hasil, payroll atau pengaitan pegawai lama otomatis. Tidak commit/push/deploy.

## Verifikasi email lamaran dan bukti penerimaan — 2 November 2026

Laravel Notifications tanpa dependency/provider baru. Migration additive000007 menambah email_verified_at dan verification_sent_at pada applications, diterapkan tanpa reset development. Pengajuan publik menyimpan Screening pending; ApplicationWorkflow menolak perubahan tahap sebelum email diverifikasi. Detail HR menampilkan status email dan menahan form tindak lanjut; input internal tidak terpengaruh. Timestamp verifikasi privat, bukan prop Viewer/publik. Profil kandidat tetap tidak ditimpa.

Notifikasi verifikasi dan bukti penerimaan mengimplementasikan ShouldQueue/ShouldBeEncrypted. Koneksi recruitment memakai jobs pada database aplikasi default, beforeCommit agar data dan antrean satu transaksi; rollback membersihkan CV. Worker menyaring job usang bila email berubah/lamaran diarsip/terverifikasi. Tautan temporarySignedRoute24jam dibuat saat email dirender, hash mengikat email saat itu. GET hanya konfirmasi, POST signed+CSRF idempotent mengantrekan receipt sekali. Verifikasi mailbox bukan verifikasi nama/CV atau keputusan lolos. Resend email saja, maksimal5pending/permintaan, cooldown10menit/lamaran, respons generik. Review independen menemukan numeric throttle Laravel memisahkan kuota user login dan guest; diganti named limiter eksplisit IP3/jam, dibuktikan tes lintas sesi.

RED awal notification/route belum ada; GREEN setelah implementasi. Tes menguji delivery dari job database terenkripsi ke mailer array tanpa jaringan, receipt setelah konfirmasi, rollback antrean/file, tamper/expiry/email berubah, GET tanpa mutasi, CSRF, replay, resend/cooldown/rate dan penolakan job usang. Journey kedua hasil akhir kini melewati verifikasi dahulu. Final **68 tests/885 assertions PASS**, Pint, lint, format:check, types:check dan build PASS.

Browser halaman kirim ulang desktop/mobile390 diperiksa, documentWidth390 tanpa overflow, header konsisten, console kosong. Screenshot `.local/email-verification-resend.png`. Tidak mengirim email nyata atau membuat kandidat development untuk QA. Mailer efektif diperiksa hanya namanya: log. Worker queue:work recruitment berjalan dalam sesi lokal persisten; SMTP/inbox nyata belum dikonfigurasi. Start-Process awal tidak bertahan, digantikan sesi worker persisten; stop-when-empty sebelumnya membuktikan koneksi valid dan antrean kosong. Panduan worker/SMTP privat dan UAT diperbarui. Tidak commit/push/deploy.

## Verifikasi alur rekrutmen menyeluruh — 2 November 2026

Ditambahkan RecruitmentJourneyTest: login HR asli melalui endpoint, buat draft, terbitkan, logout, daftar/detail/form publik, pengiriman CV, login kembali, Screening→Interview, jadwal WIB/undangan PDF, tutup lowongan, interview selesai→Offering→Diterima/Ditolak. Kedua cabang keputusan diuji; riwayat actor publik/HR dan dashboard diverifikasi, akses CV guest/Viewer ditolak, tahap terminal tidak dapat dibuka kembali. Skenario tambahan menguji rentang tanggal tepat tengah malam WIB (UTC masih tanggal sebelumnya saat pembukaan) dan menolak submit setelah kedaluwarsa tanpa menyimpan record/file.

Awal tes gagal karena ekspektasi login selalu `/hr`; perilaku aplikasi yang benar mengembalikan pengguna ke URL CV yang sebelumnya meminta login. Ekspektasi tes diperbaiki dan redirect tujuan semula dipertahankan. Tidak ditemukan kerusakan alur aplikasi pada skenario ini; perubahan hanya tes dan dokumentasi. Targeted 3 tests/252 assertions PASS. Seluruh **61 tests/796 assertions PASS**, Pint, lint, format:check, types:check dan build PASS.

Pengujian memakai database test terpisah dan Storage::fake, tanpa fixture atau reset development. Tidak mengklaim pengujian klik browser maupun UAT HR selesai. Checklist pengguna serta batas offering/keputusan, verifikasi email dan CV didokumentasikan di [RECRUITMENT-UAT.md](RECRUITMENT-UAT.md). Tidak commit/push/deploy.

## Konsistensi header publik — 2 November 2026

Landing dan seluruh mode karier menggunakan satu komponen PublicHeader: Fitur, Cara kerja, Tampilan, Lowongan, logo dan Masuk HR. Tautan bagian landing dari karier memakai `/#...`; Lowongan memiliki aria-current pada karier. Override jarak navigasi di careers.css dihapus agar satu gaya berlaku untuk kedua halaman. Browser desktop/mobile390 diperiksa tanpa overflow (documentWidth375); klik Fitur dari karier menuju `/#fitur`, lalu Lowongan kembali ke `/karier`. Console kosong. Screenshot `.local/careers-header-consistent.png`. Verifikasi: 58 tests/544 assertions, Pint, lint, format:check, types:check dan build PASS. Tidak commit/push/deploy.

## Karier publik dan form tanpa akun — 2 November 2026

Landing mempertahankan bento animasi, timeline dan status badge. Navigasi Lowongan, Masuk HR, CTA Lihat lowongan dan maksimal tiga lowongan aktif terbaru mengarah ke `/karier`. Portal menyediakan pencarian, filter departemen/jenis kerja, pagination12, detail dan form tanpa akun. Hanya published dalam periode WIB inklusif yang dapat dilihat/dilamar; HR mendapat penjelasan konsekuensi publikasi dan tautan halaman publik.

Form mewajibkan nama/email/CV PDF maksimal5MiB dan persetujuan; telepon opsional. CSRF, honeypot dan rate3/jam/IP berlaku. Lamaran baru Screening, actor/uploader publik null, respons duplikat generik. Nama/telepon pengajuan belum terverifikasi disimpan terpisah tanpa menimpa profil lama. CV publik hanya dapat diunduh HR pada lamaran terkait; Viewer tidak menerima data privat. File gagal transaksi dibersihkan. Migration000006 additive, rollback menolak data publik yang masih ada agar audit tidak hilang. Belum verifikasi email, email otomatis, CAPTCHA atau antivirus.

Review menemukan perlunya retry deadlock pada email baru bersamaan; diperbaiki dengan batas tiga percobaan untuk unique/deadlock/lock timeout dan pembersihan file. Uji dua proses pada database test membuktikan satu profil, dua lamaran berbeda dan dua CV terpisah; fixture dibersihkan. Final **58 tests/544 assertions PASS**, Pint PASS; lint, format:check, types:check dan build PASS. Tes mencakup whitelist publik, lowongan tertutup, consent/PDF/CSRF/rate limit/honeypot, duplikat dan larangan unduh CV lintas lamaran.

Browser: landing, daftar/detail/form diperiksa desktop/mobile390; pencarian tanpa hasil berfungsi, documentWidth375<=390 dan console kosong. Pengiriman formulir diuji lewat tes HTTP, bukan unggah browser. Lowongan QA sementara development dibersihkan dengan pemeriksaan identitas/versi/relasi; akun/data lain tetap ada. Screenshot `.local/landing-careers.png`, `.local/career-apply.png`, `.local/career-apply-mobile.png`. Tidak reset development, commit, push atau deploy.

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

## Jadwal interview dan PDF — 2 November 2026

Migration additive000005: interviews dengan waktu UTC, pewawancara/pembuat, lokasi/tautan, catatan privat, versi dan metadata pembatalan. Menu Rekrutmen → Jadwal interview, link dari detail lamaran Interview. Admin/Staf HR input WIB, future15–480menit, lokasi atau HTTPS. Viewer agenda dasar tanpa kontak/tautan/catatan/undangan. Pagination15 dan filter mendatang/semua/batal. Tidak edit langsung: batalkan dengan alasan lalu buat pengganti. Keluar tahap Interview membatalkan jadwal belum selesai dalam transaksi status yang sama.

Review menemukan race consistent-read snapshot MySQL setelah menunggu kunci. Diperbaiki dengan pembacaan bentrok lockForUpdate()->first() setelah lock lamaran/profil/pewawancara. Uji dua worker PHP simultan pada database test menghasilkan satu created, satu conflict dan tepat satu jadwal; fixture dibersihkan. Script lokal .local/check-interview-race.php tetap ignored.

Dompdf Laravel3.1.2/Dompdf3.1.6, remote/PHP/JS dimatikan, template Blade escaped, PDF attachment privat/no-store/nosniff dan tanpa catatan internal. Undangan berlabel demonstrasi dan tidak dikirim email. Contoh fiktif tanpa menambah DB dirender satu halaman A4 via Poppler, PNG diperiksa visual. pdftotext tidak tersedia; tidak mengklaim ekstraksi teks, tes Blade membuktikan waktu WIB09:00 dan catatan internal tidak masuk template. Contoh di .local/interview-invitation-example.pdf.

RED awal endpoint belum ada; tes PDF sempat gagal karena Composer belum selesai autoload, kemudian GREEN. Final **53 tests/468 assertions PASS**, Pint PASS, ESLint/Prettier/TypeScript/build PASS, composer validate PASS; install melaporkan tidak ada advisory. Browser agenda dan form desktop/mobile390 diperiksa, documentWidth375<=390, console kosong. Screenshot .local/interviews-ready.png. Migration development additive tanpa reset. Tidak commit/push/deploy untuk perubahan ini. Batas: belum email/kalender eksternal, edit langsung jadwal, branding perusahaan atau pengiriman undangan otomatis.
