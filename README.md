# HRIS Lite

Aplikasi HRIS mandiri yang sedang disiapkan untuk kebutuhan magang. Antarmuka berbahasa Indonesia; data pada pratinjau bersifat fiktif. Fitur operasional diimplementasikan bertahap.

## Status — Phase 1

Fondasi Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4 + Vite tersedia, dengan halaman awal bergaya berkas/editorial dan spesimen antarmuka berlabel data contoh. MySQL development/test terpisah; PHPUnit, Pint, ESLint dan Prettier dikonfigurasi. Versi persis dikunci di composer.lock dan package-lock.json.

Login, role, CRUD pegawai, ATS, interview, PDF dan CV privat **belum diimplementasikan**. Tidak ada akun demo aktif atau data pegawai. Lihat [rencana](docs/PLAN.md), [ERD](docs/ERD.md), dan [progress](docs/PROGRESS.md).

## Prasyarat

- PHP 8.3.36 atau patch lebih baru yang kompatibel; instalasi lokal sudah ditambal untuk PHP2Shell.
- Ekstensi pdo_mysql, mbstring, fileinfo, openssl, dom, xml, xmlwriter, curl, zip, ctype, tokenizer.
- Node.js 24 LTS, npm, Composer 2.
- MySQL 8.4 LTS. Mesin saat ini memakai binary Laragon 8.4.3 untuk demo loopback, bukan klaim patch MySQL terbaru.

## Menjalankan checkout yang sudah disiapkan

PowerShell dari root proyek:

```powershell
.\scripts\start-database.ps1
php artisan migrate:status
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [aplikasi lokal](http://127.0.0.1:8000). Aset build sudah tersedia di mesin ini. Untuk mengedit frontend, jalankan `npm run dev` di terminal kedua; atau `npm run build` setelah perubahan untuk memakai aset statis.

Composer lokal: `php .tools/composer.phar --version`, tanpa mengubah PATH global. MySQL memakai 127.0.0.1:3307, data .local/mysql-data dan lokasi binary dalam .local/runtime.json. Data Laragon lama tidak dipakai. Setelah restart Windows, jalankan start-database.ps1 lagi; tidak ada service/autostart baru.

Password acak hanya tersimpan di .env, .env.testing dan .local/database-credentials.json yang diabaikan Git. Jangan mengunggah file itu. Untuk menghentikan MySQL secara aman, jalankan `mysqladmin.exe --host=127.0.0.1 --port=3307 --user=root --password shutdown` dari folder bin MySQL dan masukkan password root lokal ketika diminta. Jangan force-kill proses database.

## Setup dari checkout baru

```powershell
.\scripts\install-composer.ps1
php .tools/composer.phar install
npm ci
Copy-Item .env.example .env
Copy-Item .env.testing.example .env.testing
```

Sediakan MySQL lokal yang sudah diinisialisasi secara aman. Sebagai administrator database, jalankan SQL berikut; ganti placeholder dengan dua password acak berbeda:

```sql
CREATE DATABASE hris_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE hris_lite_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hris_dev'@'localhost' IDENTIFIED BY '<PASSWORD_DEV_ACAK>';
CREATE USER 'hris_test'@'localhost' IDENTIFIED BY '<PASSWORD_TEST_ACAK>';
GRANT ALL PRIVILEGES ON hris_lite.* TO 'hris_dev'@'localhost';
GRANT ALL PRIVILEGES ON hris_lite_test.* TO 'hris_test'@'localhost';
```

Isi password masing-masing pada .env dan .env.testing. Sesuaikan port; template memakai 3307. start-database.ps1 khusus direktori data proyek yang sudah diinisialisasi; gunakan service MySQL sendiri jika setup berbeda. Jangan initialize direktori data lama.

```powershell
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
php artisan migrate --env=testing
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

APP_DEBUG default false. Aktifkan hanya sementara di .env untuk debugging lokal, bukan pada lingkungan publik. MAIL_MAILER=log; tidak ada pengiriman email otomatis.

## Pemeriksaan

```powershell
php .tools/composer.phar validate --strict
php .tools/composer.phar check-platform-reqs
php vendor/bin/pint --test
npm run lint
npm run format:check
npm run types:check
npm run build
php artisan test
```

Tes memakai .env.testing dan akun hris_test. Jalankan migration test dahulu. Suite membuktikan koneksi MySQL serta akun test tidak bisa membaca database development. Build diperlukan untuk smoke test HTTP yang membaca manifest Vite. Gunakan `npm run format` dan `php vendor/bin/pint` untuk memperbaiki format.

Pada sandbox Codex Windows, socket localhost atau akses file PHP/Pint bisa ditolak meskipun terminal normal berhasil. Gunakan izin eksekusi yang sesuai; jangan mengubah validasi aplikasi untuk melewati sandbox.

## Arsitektur dan batasan

Laravel menangani route, session dan response Inertia; React merender halaman. Entry resources/js/app.tsx, root resources/views/app.blade.php, middleware HandleInertiaRequests, halaman resources/js/pages/welcome.tsx. Token warna, tipografi dan aturan anti-AI-slop ada di [DESIGN.md](DESIGN.md). Tidak ada API atau layanan eksternal terpisah.

Belum ada route privat/data domain. Tiga migration bawaan hanya menyediakan user/session, cache dan jobs. Schema HR dan otorisasi server-side dilanjutkan pada Phase 2. Welcome memuat indeks dan spesimen antarmuka dengan data contoh yang diberi label jelas; bukan data nyata dan bukan route privat.

Route `/lab` menampilkan komponen impor `ink-orbit-features` sebagai spesimen (tema gelap, copy Indonesia). Halaman ini bukan bagian alur produk dan sengaja tidak mengikuti aturan `DESIGN.md`; lihat bagian "Karantina" di sana.

Screenshot tahap ini: jalankan aplikasi dan ambil halaman awal pada desktop/mobile tanpa devtools. Walkthrough wawancara tiga menit, akun demo, screenshot fitur dan panduan PDF/CV ditambahkan setelah fitur terkait bekerja.
