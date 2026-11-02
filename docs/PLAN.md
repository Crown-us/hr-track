# HRIS Lite Implementation Plan

> Untuk pelaksana: gunakan superpowers:executing-plans untuk implementasi per tugas di checkout ini. Jangan implementasikan seluruh fase sekaligus.

**Goal:** Prototipe HRIS berbahasa Indonesia untuk demonstrasi portofolio wawancara magang, dengan alur pegawai dan rekrutmen yang benar-benar bekerja.

**Architecture:** Monolit Laravel dengan Inertia dan React TypeScript. Laravel menangani session, otorisasi, validasi, query dan transaksi; React menampilkan props terpilih tanpa akses database langsung. Tidak ada repository abstraction atau API terpisah.

**Tech Stack:** Target Laravel 13, PHP 8.3 yang tersedia, Inertia, React, TypeScript, Tailwind, komponen shadcn/ui, Vite, MySQL, Pint, ESLint, Prettier, PHPUnit yang kompatibel PHP 8.3.

**Spec:** [Brief asli](BRIEF.md), [skema database](ERD.md).

## Status dan batas pekerjaan

Tanggal inspeksi: 1 November 2026 (Asia/Jakarta). Phase 0 dan fondasi Phase 1 sudah dijalankan setelah persetujuan pengguna. Laravel/Inertia/React, lockfile, MySQL development/test, halaman awal dan pengujian fondasi tersedia. Detail bukti dan keputusan ada di [PROGRESS.md](PROGRESS.md). Fitur bisnis Phase 2–7 belum diimplementasikan.

Pembaruan 2 November: subset autentikasi Phase 2 tersedia: Fortify login/logout, aktivasi via undangan, role dan gate halaman `/hr`. Data bisnis masih contoh. Detail scope dan batasnya di [AUTH-PLAN.md](AUTH-PLAN.md); belum menandai seluruh Phase 2 selesai karena migration domain/policies CRUD belum dikerjakan.

Pembaruan pegawai: subset Phase3 CRU, arsip terkendali, restore, audit dan file pendukung privat tersedia; lihat [EMPLOYEE-PLAN.md](EMPLOYEE-PLAN.md). Direktori/dashboard pegawai memakai database; rekrutmen tetap contoh. Ketentuan baru pengguna menambahkan restore Admin dan bukti PDF wajib saat arsip, mengesampingkan batas restore awal di ERD.

Pembaruan lowongan: subset Phase4 lowongan internal tambah/baca/edit/status, pencarian/filter/pagination dan count dashboard aktual tersedia. Lihat [VACANCY-PLAN.md](VACANCY-PLAN.md). Pipeline kandidat, lamaran/CV, arsip lowongan dan halaman karier publik belum diimplementasikan; Phase4 belum dianggap selesai.

Pembaruan ATS: input profil baru/terdaftar, lamaran, CV privat berversi, pipeline database dan transisi/riwayat tersedia. Dashboard sudah memakai angka aktual. Batas hemat scope: belum edit/hapus profil, jadwal interview/undangan atau portal publik; lihat [APPLICATION-PLAN.md](APPLICATION-PLAN.md). Phase4/5 belum ditandai selesai seluruhnya.

## Phase 0 — Bukti lingkungan

| Pemeriksaan | Hasil aktual |
| --- | --- |
| `Get-ChildItem -Force` | Direktori awal kosong |
| `git status --short` | Gagal: bukan repository Git |
| AGENTS.md di root workspace dan D:\\ | Tidak ditemukan |
| `php --version` | PHP 8.3.31 CLI |
| `php --ini`, `php -m` | php.ini dimuat; pdo_mysql, mbstring, fileinfo, openssl, dom, xml, curl, zip tersedia; pdo_sqlite tidak tercantum |
| `node --version`, `npm --version` | v24.17.0, 11.13.0 |
| `git --version` | 2.54.0.windows.1 |
| `Get-Command composer/mysql` | Tidak ditemukan di PATH; Composer juga tidak ditemukan di dua lokasi umum yang diperiksa |
| `docker version` | Client 29.8.2 tersedia; koneksi Docker Desktop Linux engine gagal |
| Pemeriksaan service MySQL/MariaDB | Akses Service Control Manager ditolak; bukan bukti server tidak terpasang |

Blocker: Composer perlu disediakan dan koneksi MySQL development/test perlu dibuktikan sebelum migration/test. Jangan mengganti MySQL dengan SQLite secara diam-diam. Docker MySQL merupakan alternatif apabila engine tersedia, bukan dependency wajib. Jangan mengubah konfigurasi PHP global tanpa kebutuhan yang terbukti.

### Pembaruan keamanan — 1 November 2026

Atas permintaan pengguna, PHP WinGet `PHP.PHP.8.3` berhasil diperbarui dari 8.3.31 ke **8.3.36** untuk patch PHP2Shell (CVE-2026-109618/CVE-2026-109619). WinGet memverifikasi hash installer resmi dan selesai dengan exit 0. `php --version` serta `php-cgi.exe -v` mengonfirmasi 8.3.36; hash php.ini dan daftar modul tidak berubah dibanding backup sebelum upgrade. Pemeriksaan runtime untuk pdo_mysql, mbstring, fileinfo, openssl, dom, xml, curl dan zip berhasil.

Tidak ditemukan proses php/php-cgi/httpd/nginx/w3wp aktif pada pemeriksaan setelah upgrade, sehingga tidak ada service yang direstart. Cakupan verifikasi adalah instalasi PHP yang ditemukan melalui PATH; bukan audit semua instalasi tersembunyi atau investigasi kompromi. Backup konfigurasi disimpan di folder temporary pengguna `hris-php-security-backup-20261101`, bukan source proyek. Gunakan 8.3.36 atau patch lebih baru sebagai baseline selanjutnya; tabel inspeksi di atas mempertahankan hasil awal sebelum perubahan.

Sumber: [PHP 8.3.36 security release](https://www.php.net/releases/8_3_36.php), [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5).

### Pilihan versi dan sumber

- [Laravel release policy](https://laravel.com/docs/13.x/releases): Laravel 13 mendukung PHP 8.3–8.5. Targetkan stable 13.x terbaru yang dapat di-resolve pada PHP lokal, lalu kunci dependency; belum ada hasil resolver.
- [Official React starter composer.json](https://github.com/laravel/react-starter-kit/blob/main/composer.json) dan [package.json](https://github.com/laravel/react-starter-kit/blob/main/package.json) diperiksa. Branch main saat inspeksi memakai Inertia 3, PHPUnit 12 dan tooling frontend `vp`; jangan menyalin main tanpa audit kompatibilitas PHP dan kebutuhan Vite/ESLint/Prettier dari brief.
- Pilihan utama: skeleton Laravel stable ditambah paket Inertia/React stable kompatibel, konfigurasi Vite eksplisit, serta autentikasi Laravel Fortify. Starter resmi hanya dipakai jika versi stabilnya memenuhi seluruh toolchain; hindari WorkOS dan layanan eksternal.
- [Laravel Dompdf manifest](https://github.com/barryvdh/laravel-dompdf/blob/master/composer.json) mencantumkan dukungan Illuminate 13. Gunakan rilis stable yang lolos resolver, bukan dev-master. PDF sederhana berbasis Blade; aset lokal saja.
- Exact versions PHP package dan npm package dicatat dari lockfile pada scaffold, bukan ditebak di dokumen ini.

## Global constraints

- Semua halaman/form/error aplikasi berbahasa Indonesia; tanggal tampilan Asia/Jakarta, timestamp database UTC.
- Data seed seluruhnya fiktif; domain email `example.test`; tanpa NIK atau data pasien.
- Kerja langsung di `D:\HR Management`; pertahankan docs saat scaffold. Tidak ada deploy, publish, email otomatis atau push remote.
- Role `admin`, `hr_staff`, `viewer`; semua private route memakai auth dan policy. UI yang menyembunyikan tombol bukan kontrol keamanan.
- Tidak ada kredensial production di source atau Git. Demo seeder hanya local/testing, dengan password dari environment lokal dan hash Laravel.
- Target 5–7 hari; payroll, absensi, portal kandidat publik, multi-tenant, manajemen akun UI, dark mode, email, dan kontrak lengkap di luar MVP.
- Contract-expiration preview ditunda karena schema MVP tidak memiliki kontrak; jangan tampilkan angka palsu.

## Halaman dan desain

| Route | Isi dan interaksi |
| --- | --- |
| `/login` | Email, password, validasi, rate limit; registrasi publik dimatikan |
| `/dashboard` | Pegawai non-arsip, lowongan published dalam rentang tanggal, kandidat unik, interview mendatang; pipeline dihitung dari applications, aktivitas dari history |
| `/employees`, `/employees/create`, `/employees/{id}`, `/employees/{id}/edit` | Tabel, cari nama/nomor/email, filter departemen/status, sort whitelist, 15 baris per halaman, CRUD/arsip |
| `/vacancies`, `/vacancies/create`, `/vacancies/{id}`, `/vacancies/{id}/edit` | Tabel, cari judul, filter departemen/status, CRUD/arsip |
| `/applicants`, `/applicants/create`, `/applicants/{id}`, `/applicants/{id}/edit` | Identitas kandidat, aplikasi per lowongan, CV privat, timeline status dan wawancara |
| `/applications` | Board aksesibel per status; filter lowongan, pagination per kolom, tombol perpindahan status; drag-and-drop tidak wajib |
| `/interviews`, `/interviews/create`, `/interviews/{id}/edit` | Agenda, jadwal, pewawancara, lokasi/link, catatan, unduh undangan |

Shell: sidebar responsif, topbar, breadcrumb, fokus keyboard, label form, error dekat input, empty/loading/error states, konfirmasi arsip, status tidak hanya dibedakan warna. Warna: #F8FAFC, #0F172A, #4F46E5, #10B981, abu-abu muted. Di mobile tabel dapat digulir tanpa memotong aksi; form satu kolom. Grafik berupa ringkasan/bar sederhana dari query nyata; tidak menambah library chart sebelum dibutuhkan.

## Hak akses yang diusulkan

| Aksi | admin | hr_staff | viewer |
| --- | --- | --- | --- |
| Dashboard, daftar/detail operasional | Ya | Ya | Ya |
| Tambah/edit pegawai, lowongan, kandidat/aplikasi | Ya | Ya | Tidak |
| Arsip pegawai/lowongan/kandidat | Ya | Tidak | Tidak |
| Ubah pipeline, jadwalkan/edit/hapus interview | Ya | Ya | Tidak |
| Upload/download CV, unduh PDF interview | Ya | Ya | Tidak |

Viewer tidak menerima storage path, catatan privat atau CV melalui Inertia props. Semua role bersifat global untuk organisasi demo tunggal; belum ada pembatasan per departemen. Master departemen/jabatan melalui seeder dahulu.

## Struktur implementasi

- `app/Models/`: sepuluh model sesuai ERD; relasi eksplisit dan casts enum/date.
- `app/Enums/{Role,EmploymentStatus,EmploymentType,VacancyStatus,ApplicationStatus}.php`: nilai domain terpusat.
- `app/Http/Controllers/{Dashboard,Employee,Vacancy,Applicant,Application,ApplicationStatus,Interview,ApplicantDocument}Controller.php`: query terpaginasikan, policy dan response Inertia/download.
- `app/Http/Requests/`: Store/Update untuk Employee, Vacancy, Applicant, Application, Interview; ChangeApplicationStatusRequest dan StoreApplicantDocumentRequest.
- `app/Policies/`: policy per resource; `app/Providers/AppServiceProvider.php` untuk gate dashboard jika diperlukan.
- `app/Services/ApplicationWorkflow.php`: `create(Applicant $applicant, Vacancy $vacancy, User $actor, array $data): Application`; `transition(Application $application, ApplicationStatus $target, User $actor, ApplicationStatus $expected): Application`.
- `app/Services/ApplicantDocumentStorage.php`: upload privat dengan nama UUID, hapus file jika insert metadata gagal; unduhan berdasarkan model ID, bukan input path.
- `resources/js/layouts/app-layout.tsx`, `components/ui/`, `components/data-table.tsx`, `types/hr.ts`, `pages/{dashboard,employees,vacancies,applicants,applications,interviews}/`: props bertipe, reusable forms dan shell.
- `resources/views/pdf/interview-invitation.blade.php`: undangan berlabel dokumen demonstrasi, escaped values, remote fetching dan embedded PHP dimatikan.
- `database/migrations/`, `database/factories/`, `database/seeders/`, `tests/Feature/`, `tests/Unit/`: schema, data deterministik dan regression tests.

## Aturan bisnis utama

Pipeline: `screening -> interview|rejected`, `interview -> offering|rejected`, `offering -> accepted|rejected`; accepted/rejected terminal. Tidak ada loncat, mundur atau reopen pada MVP. Identitas kandidat terpisah dari lamaran; satu kandidat boleh banyak lowongan, tetapi satu kombinasi kandidat-lowongan hanya sekali.

Create application hanya untuk lowongan published yang sedang dibuka, mulai screening dan tulis history awal dengan old_status null. Perubahan status memakai transaksi dan row lock, memeriksa status yang diharapkan agar request stale mendapat 409, lalu update status dan insert history secara atomik. Actor diambil dari session; tidak boleh berasal dari payload. Edit generik tidak menerima status.

Interview hanya untuk aplikasi berstatus interview; waktu mulai di masa depan, durasi 15–480 menit, interviewer user admin/hr_staff aktif, minimal lokasi atau HTTPS meeting URL. Tolak bentrok interval interviewer; simpan UTC, tampilkan WIB. Status lain tidak menghapus history interview; jadwal yang belum berlangsung dibatalkan dalam transaksi saat keluar dari interview agar dashboard tetap benar.

CV hanya PDF maksimal 5 MiB (5120 KiB), MIME/content diperiksa server, filename display dibersihkan, file di disk lokal privat tanpa public symlink. Unduh sebagai attachment dengan nosniff setelah policy. PDF invitation tidak mengambil URL meeting sebagai resource. Tidak ada pengiriman email.

## Review focus

1. Request stale bersamaan tidak menulis dua history transisi; diuji di tugas ATS pada MySQL.
2. Pegawai tidak boleh memilih jabatan departemen lain; diuji validasi dan FK gabungan.
3. Payload memalsukan role/status/actor/path tidak boleh menaikkan akses; diuji per resource.
4. Rentang tanggal dan konversi WIB/UTC tidak menggeser hari interview; diuji waktu batas tengah malam.
5. Arsip tidak memutus audit, duplicate unique, atau membocorkan dokumen; diuji pada CRUD dan download.

## Urutan pelaksanaan dan acceptance checks

### Phase 1 — Rancangan dan scaffold (hari 1)

- [x] Simpan brief, hasil inspeksi, rencana, ERD dan risiko.
- [x] Setelah rancangan disetujui, sediakan Composer lokal dengan verifikasi installer; buktikan koneksi database development dan test yang terpisah.
- [x] Scaffold di staging directory workspace, audit manifest stable dan scripts sebelum install, lalu salin hanya file yang tidak menimpa docs. Jangan menjalankan create-project ke folder docs yang tidak kosong.
- [x] Buat `.gitignore`, `.env.example`, `composer.json`, `package.json`, `vite.config.ts`, `tsconfig.json`, ESLint/Prettier configs. Konfigurasi MySQL, locale id, session; env asli diabaikan Git. Inisialisasi Git lokal sesudah ignore siap.
- [x] Resolve dependency pada PHP lokal; `composer validate --strict`, `composer check-platform-reqs`, `php artisan about`, `npm run build`. Acceptance: semua exit 0, tidak ada paket prerelease tak disengaja. Scaffold dapat dijalankan lokal, belum menjanjikan fitur HR.

### Phase 2 — Authentication dan data (hari 1–2)

- [ ] Tulis `tests/Feature/Auth/AuthenticationTest.php` dan `RoleAuthorizationTest.php`: guest redirect login; session login/logout; rate limit; role palsu tidak mengubah user; mutation viewer 403. Jalankan sebelum implementasi untuk memastikan kegagalan yang relevan.
- [ ] Implementasikan Fortify login/logout tanpa registrasi publik, role enum dan policies; migrasi berurutan sesuai ERD, factory tiap model, DatabaseSeeder dan DemoSeeder local/testing.
- [ ] `tests/Feature/DatabaseConstraintsTest.php`: unique email/nomor pegawai, unique aplikasi, FK yatim dan mismatch jabatan-departemen ditolak MySQL; seeder production ditolak.
- [ ] Jalankan `php artisan migrate --seed` pada database development baru dan `php artisan test --filter=AuthenticationTest`, `--filter=RoleAuthorizationTest`, `--filter=DatabaseConstraintsTest` pada database test; cek akun demo dari environment, tanpa mencetak password/log PII.

### Phase 3 — Shell, dashboard, pegawai (hari 2–3)

- [ ] Tulis `tests/Feature/EmployeeTest.php`: create/show/update/archive, duplicate email/number 422, department mismatch 422, viewer 403, arsip hr_staff 403; filter/sort/pagination benar dan sort tidak dikenal ditolak.
- [ ] Implementasikan model Employee, request, policy, controller, halaman dan shell. Query eager-load department/position, sort whitelist, pagination 15 (maksimal 100).
- [ ] Tulis `tests/Feature/DashboardTest.php`: count berdasarkan fixture DB termasuk kandidat unik vs jumlah aplikasi; soft-deleted tidak terhitung. Implementasikan query agregasi dan empty states.
- [ ] Jalankan kedua test, TypeScript dan build; cek browser 375px dan 1440px, tab keyboard, form error, navigation dan empty states. Tidak menulis tes hanya untuk warna/dekorasi.

### Phase 4 — Lowongan dan ATS (hari 3–5)

- [ ] `tests/Feature/VacancyTest.php`: CRUD/role, tanggal penutupan sebelum pembukaan 422, status tidak dikenal 422, pencarian/filter/pagination, arsip dengan aplikasi tetap menjaga audit.
- [ ] Implementasikan Vacancy CRUD sesuai pola Employee.
- [ ] `tests/Feature/ApplicationTest.php`, `ApplicationStatusTest.php`: identitas satu kandidat pada dua lowongan; duplikat ditolak; lowongan tertutup ditolak; semua edge pipeline legal/ilegal; actor/history awal; rollback history jika operasi gagal; request stale 409 dan konkurensi MySQL.
- [ ] Implementasikan Applicant CRUD, ApplicationWorkflow, history immutable, detail timeline dan board aksesibel. Kandidat dengan aplikasi diarsip bersama aplikasinya dalam transaksi; download/transition aplikasi arsip ditolak.
- [ ] Jalankan test fase ini dan build; verifikasi jumlah query tidak bertambah per row karena eager loading.

### Phase 5 — Interview, PDF dan CV (hari 5–6)

- [ ] `tests/Feature/InterviewTest.php`: invalid date/duration/url, konflik interviewer, status aplikasi salah, konversi WIB/UTC dan pembatalan saat meninggalkan tahap interview.
- [ ] `tests/Feature/ApplicantDocumentTest.php`: guest login redirect, viewer 403, HR PDF upload/download berhasil, PDF palsu/terlalu besar 422, path payload diabaikan, arsip 404, file hilang 404 dan storage cleanup saat DB gagal.
- [ ] `tests/Feature/InterviewInvitationTest.php`: authorized 200, application/pdf, attachment header, body diawali `%PDF-`, viewer 403; template berlabel demo dan escaped content.
- [ ] Implementasikan controller/request/policy, private disk, storage service dan Blade PDF. PDF generator tidak boleh mengakses resource remote.
- [ ] Jalankan ketiga test dan build, buka PDF hasil demo untuk inspeksi isi dan layout. Uji ulang download setelah logout.

### Phase 6 — Quality gate (hari 6–7)

- [ ] Sediakan scripts `lint` (ESLint), `format:check` (Prettier), `types:check` (tsc --noEmit), `build` (Vite) sesuai manifest aktual.
- [ ] Jalankan `vendor/bin/pint --test`, `npm run lint`, `npm run format:check`, `npm run types:check`, `php artisan test`, `npm run build`; gunakan executable Windows yang sesuai bila diperlukan. Catat exit code dan hasil aktual.
- [ ] Jalankan migration dari kosong hanya pada database test disposable, bukan development yang berisi pekerjaan user. Audit route auth/policy, N+1, mass assignment, CV exposure, secret dan production debug configuration.
- [ ] Perbaiki failure yang terbukti; ulangi hanya pemeriksaan yang terdampak lalu quality gate akhir.

### Phase 7 — Dokumentasi demo (hari 7)

- [ ] Finalisasi `README.md`: prerequisites/version locks, setup PowerShell, konfigurasi MySQL, akun demo development, cara seed/test/build, fitur aktual, arsitektur dan batasan.
- [ ] Tulis `docs/DEMO.md`: 0:00 konteks proyek independen, 0:20 dashboard, 0:45 CRUD pegawai, 1:15 lowongan/kandidat, 1:40 transisi dan audit, 2:10 interview/PDF/CV, 2:40 role/tests/limitasi.
- [ ] Tulis `docs/SCREENSHOTS.md`: login, dashboard, pegawai, pipeline, timeline, interview; hanya fixture fiktif, viewport konsisten, tanpa env/password.
- [ ] Laporkan setiap fase: file berubah, perintah, hasil nyata, blocker, langkah berikut. Jangan menyatakan aplikasi selesai sebelum gate benar-benar lolos.

## Risiko dan keputusan yang perlu diketahui

- Setup Composer/MySQL dapat mengurangi jatah 5–7 hari; estimasi mengasumsikan environment siap pada hari pertama.
- Starter main bergerak cepat; kombinasi toolchain wajib ditentukan dari stable release/lockfile yang benar-benar terpasang. Tidak memaksa upgrade PHP global agar starter terbaru bisa dipakai.
- MySQL test wajib untuk FK/locking; SQLite tidak merepresentasikan seluruh behavior.
- Role viewer sengaja dibatasi untuk CV/PDF/catatan privat; ini keputusan desain yang dapat ditinjau sebelum implementasi.
- Soft delete mempertahankan unique values dan history, bukan fitur pemulihan atau retention otomatis. CV tidak otomatis dipindai antivirus; hanya data demo lokal, bukan sistem production siap operasi.
- Konflik interview memerlukan serialisasi write per interviewer agar validasi paralel tidak lolos; uji integrasi MySQL, bukan hanya validasi form.

