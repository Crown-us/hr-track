# HRIS Lite

Modul **Pelatihan** tersedia di `/trainings`: jadwal dan pemateri, materi PDF privat berversi, peserta dari pegawai aktif, kehadiran dan pre-test/post-test0–100. Admin/Staf HR mengelola; Viewer hanya membaca jadwal serta jumlah peserta. Ringkasan memakai pasangan nilai lengkap dan menampilkan peningkatan dalam poin, bukan persentase efektivitas. Skor kosong tidak dianggap0. Post-test dicatat setelah sesi berakhir dan peserta hadir. Nilai tetap dapat dikoreksi setelah pelatihan selesai; perubahan selalu diaudit. Riwayat peserta tetap tersimpan jika pegawai kemudian diarsipkan.

Pelatihan dibatalkan dengan alasan tanpa menghapus data. Sesi yang pernah memiliki nilai tidak bisa digeser waktunya, termasuk setelah nilai dikosongkan. Upload PDF maksimal5MiB pada disk training_materials privat; versi lama tetap tersedia untuk Admin/Staf HR. Belum sertifikat, notifikasi email pelatihan, impor massal, penghapusan peserta atau ekspor laporan. Lihat [TRAINING-PLAN.md](docs/TRAINING-PLAN.md).

Panduan pengujian alur rekrutmen dan checklist pengguna HR: [RECRUITMENT-UAT.md](docs/RECRUITMENT-UAT.md).

Offering → pegawai tersedia melalui detail lamaran → **Penawaran, respons & data pegawai**. Admin/Staf HR mencatat penempatan/tanggal/ketentuan, merevisi sebelum respons, mengunduh ringkasan PDF internal, lalu mencatat respons dengan bukti PDF privat wajib. Menerima→Diterima; menolak/ditarik HR→Ditolak. Keputusan Diterima tidak dapat melewati penawaran yang diterima. Respons bertanggal sebelum revisi terakhir atau penerimaan melewati batas ditolak; revisi penawaran dahulu bila perlu memperpanjang batas. Ringkasan bukan surat resmi perusahaan atau kontrak.

Konversi memerlukan lamaran dan penawaran diterima serta konfirmasi identitas. Nama/telepon terisi dari profil dan dapat dikoreksi HR; nomor pegawai/email kerja wajib diisi. Penempatan/status/tanggal mengikuti penawaran. Satu profil hanya dapat menjadi satu pegawai, termasuk bila pegawai sudah diarsipkan; tidak membuat akun login atau menimpa profil pelamar. Penawaran/audit/bukti hanya untuk Admin/Staf HR. Lamaran lama yang sudah Diterima tanpa catatan penawaran tidak otomatis dikonversi atau dibuatkan bukti mundur; tinjau dan gunakan direktori pegawai secara manual. Lihat [OFFERING-PLAN.md](docs/OFFERING-PLAN.md).

Aplikasi HRIS mandiri yang sedang disiapkan untuk kebutuhan magang. Antarmuka berbahasa Indonesia; data pada pratinjau bersifat fiktif. Fitur operasional diimplementasikan bertahap.

## Status — fondasi dan autentikasi

Fondasi Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4 + Vite tersedia, dengan halaman awal bergaya berkas/editorial dan spesimen antarmuka berlabel data contoh. MySQL development/test terpisah; PHPUnit, Pint, ESLint dan Prettier dikonfigurasi. Versi persis dikunci di composer.lock dan package-lock.json.

Login/logout Fortify, aktivasi akun, master departemen dan jabatan, pegawai dan lowongan tersedia. Pegawai dapat ditambah/diedit/diarsipkan/dipulihkan sesuai role. Profil pelamar (halaman `/applicants`), lamaran, pipeline, riwayat tahap dan CV privat tersimpan di database. Jadwal interview, undangan PDF dan portal karier dengan form lamaran tanpa akun tersedia. Tidak ada kredensial bawaan. Lihat [rencana](docs/PLAN.md), [ERD](docs/ERD.md), dan [progress](docs/PROGRESS.md).

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

APP_DEBUG default false. Aktifkan hanya sementara di .env untuk debugging lokal, bukan pada lingkungan publik. MAIL_MAILER=log untuk pengembangan; notifikasi otomatis pelamar ditulis ke log setelah worker antrean memprosesnya, bukan ke inbox nyata.

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

Route `/hr` memakai session web dan gate untuk role admin/hr_staff/viewer; angka pegawai, lowongan, pelamar dan pipeline berasal dari database. Direktori `/employees`, `/vacancies` dan `/applications` memakai policy per aksi. Tabel domain pegawai, lowongan, applicants, applications, application_status_histories dan applicant_documents tersedia; pratinjau lokal tetap terpisah.

Route `/lab` menampilkan komponen impor `ink-orbit-features` sebagai spesimen (tema gelap, copy Indonesia). Halaman ini bukan bagian alur produk dan sengaja tidak mengikuti aturan `DESIGN.md`; lihat bagian "Karantina" di sana.

Screenshot tahap ini: jalankan aplikasi dan ambil halaman awal pada desktop/mobile tanpa devtools. Walkthrough wawancara tiga menit, akun demo, screenshot fitur dan panduan PDF/CV ditambahkan setelah fitur terkait bekerja.

## Pratinjau ruang kerja HR

Buka http://127.0.0.1:8000/preview/hr pada environment local. Tersedia ringkasan, direktori pegawai serta board/daftar rekrutmen dengan filter dan detail kandidat. Data fiktif berada di resources/js/data/hr-preview.ts; perubahan belum disimpan. Route ini404 di production dan tidak menggantikan implementasi autentikasi/otorisasi. Pelatihan, hubungan kerja, dan laporan belum tersedia.

## Pegawai dan pengarsipan

Setelah migration dari checkout baru, isi master pilihan departemen/jabatan:

```powershell
php artisan db:seed --class=EmployeeMasterSeeder
```

Seeder idempoten menyediakan 5 departemen dan 10 jabatan awal; tidak menambahkan pegawai atau mengubah akun. Master dapat disesuaikan lewat seeder/database oleh pengelola; UI manajemen master belum tersedia.

`/employees` mendukung pencarian nama/nomor/email, filter departemen/status, sort whitelist dan pagination15. Admin serta Staf HR bisa tambah/edit; Viewer hanya membaca. Nomor pegawai dan email unik, termasuk catatan arsip. Jabatan harus berada pada departemen yang sama, dijaga validasi dan FK gabungan database. Form lama ditolak melalui versi integer agar tidak menimpa perubahan pengguna lain.

Penghapusan dari direktori berarti **arsip/soft delete**. Hanya Admin dapat mengarsipkan dengan alasan minimal20 karakter, PDF pendukung maksimal5MiB, konfirmasi nomor pegawai dan pernyataan pemeriksaan. Riwayat menyimpan actor dari session, waktu UTC (tampilan WIB), alasan dan perubahan. PDF disimpan pada disk `employee_records` yang privat, tidak disajikan lewat URL storage publik, dan hanya Admin dapat mengunduhnya sebagai attachment setelah otorisasi. File dibersihkan jika transaksi database gagal. Tidak ada endpoint pemusnahan permanen.

Admin membuka tab Arsip untuk melihat detail dan memulihkan pegawai dengan alasan serta konfirmasi nomor. Riwayat dan dokumen sebelumnya tetap disimpan. Untuk pegawai resign, gunakan status Nonaktif bila catatan masih perlu terlihat; arsip lebih cocok untuk duplikat/salah input. Ini pilihan workflow aplikasi, bukan klaim kewajiban hukum. Kebijakan retensi, pemusnahan permanen dan persetujuan dua orang perlu ditentukan perusahaan sebelum tahap tersebut dibuat. Belum ada antivirus/sanitasi PDF; lampiran diperlakukan sebagai dokumen tidak tepercaya.

## Lowongan internal

Menu Rekrutmen membuka `/vacancies` dengan tab Lowongan dan Pipeline kandidat. Admin/Staf HR dapat menambah, melihat, mengedit dan mengubah status draft/dibuka/ditutup; Viewer hanya membaca. Form memuat departemen, jenis kerja, lokasi, deskripsi/persyaratan dan tanggal buka/tutup. Pencarian judul/lokasi, filter departemen/status, sorting whitelist dan pagination15 tersedia. Form lama ditolak melalui version+row lock.

Status Dibuka dihitung aktif pada dashboard dan ditampilkan ke publik hanya dalam rentang tanggal buka/tutup menurut tanggal WIB (kedua batas inklusif). Sebelum tanggal buka tampil Terjadwal; setelah tanggal tutup tampil Berakhir. Menutup lowongan menyembunyikannya dari karier tanpa menghapus catatan. Belum ada endpoint hapus lowongan. Pipeline di tab sebelah memakai lamaran tersimpan. Detail di [VACANCY-PLAN.md](docs/VACANCY-PLAN.md).

## Pelamar dan lamaran

Buka Rekrutmen → Pipeline kandidat (`/applications`). Admin/Staf HR dapat mencatat profil baru atau memilih Pelamar terdaftar melalui pencarian (20 hasil). Email profil unik dan dinormalisasi lowercase; identitas lama tidak ditimpa ketika membuat lamaran lain. Satu profil dapat melamar ke beberapa lowongan; pasangan profil/lowongan tidak boleh duplikat. Lowongan harus sedang menerima lamaran dan tanggal lamaran berada dalam periodenya, tidak di masa depan.

CV PDF opsional saat input internal dan dapat ditambahkan dari detail sebagai versi baru. Maksimal5MiB, ekstensi/MIME/header PDF diperiksa; disk applicant_documents privat serve=false. CV unggahan HR dibagikan antar lamaran profil; CV publik dibatasi ke lamaran terkait. Unduh attachment/nosniff hanya bagi Admin/Staf HR, dengan pemeriksaan kepemilikan profil dan lamaran. Viewer tidak menerima CV, telepon, catatan HR atau catatan privat riwayat. Belum ada antivirus/sanitasi PDF.

Lamaran dimulai Screening. Transisi manual: Screening→Interview/Ditolak, Interview→Offering/Ditolak, Offering→Diterima/Ditolak. Diterima/Ditolak terminal, tanpa reopen. Riwayat menyimpan actor session, waktu UTC (tampilan WIB) dan catatan opsional. Row lock+version menolak perubahan dari formulir lama. File dibersihkan jika transaksi DB gagal.

Board/daftar memiliki search/filter dan pagination15. Jumlah tahap mencakup seluruh hasil filter; kartu board hanya halaman aktif. Dashboard memakai angka dan aktivitas aktual, tanpa jadwal interview fiktif. Belum ada edit/hapus profil atau penghapusan dokumen. Scope di [APPLICATION-PLAN.md](docs/APPLICATION-PLAN.md).

Portal `/karier` menyediakan pencarian, filter, detail dan form lamaran tanpa akun. Landing menampilkan maksimal tiga lowongan aktif. Form mewajibkan CV PDF dan persetujuan penggunaan data, memakai CSRF, honeypot dan batas tiga pengiriman per jam/IP (termasuk permintaan tidak valid). Lamaran baru masuk Screening dan menunggu verifikasi email sebelum HR dapat mengubah tahap; duplikat mendapat respons generik tanpa membocorkan profil. Identitas pengajuan disimpan terpisah tanpa menimpa profil lama. Verifikasi mailbox tidak membuktikan identitas atau kebenaran CV. Belum CAPTCHA atau pemindaian antivirus. Lihat [CAREERS-PLAN.md](docs/CAREERS-PLAN.md).

Verifikasi pelamar menggunakan Laravel Notifications dan temporary signed URL 24 jam. GET membuka konfirmasi; POST dengan CSRF menandai email terverifikasi per lamaran dan mengantrekan bukti penerimaan. Bukti bukan keputusan lolos seleksi. `/karier/verifikasi/kirim-ulang` memberi respons generik, batas tiga permintaan/jam/IP, cooldown10menit per lamaran dan maksimal lima lamaran pending per permintaan. Lamaran publik lama yang belum memiliki penanda verifikasi perlu memakai kirim ulang sebelum tahap dapat diubah.

Jalankan worker dengan `php artisan queue:work recruitment --queue=recruitment-mail --tries=3 --timeout=60`. Koneksi antrean recruitment sengaja memakai database aplikasi yang sama: insert pekerjaan terenkripsi, lamaran dan file metadata satu transaksi; rollback membersihkan CV. Konfigurasi lokal aktif memakai mailer log, sehingga belum mengirim ke inbox. Untuk SMTP perusahaan, isi konfigurasi MAIL_* privat, alamat pengirim dan APP_URL yang benar (HTTPS di production), lalu restart worker. Jangan commit kredensial atau membagikan log yang memuat tautan verifikasi. Periksa failed_jobs bila pengiriman gagal; retry dapat menyebabkan email terkirim ulang. Panduan di [EMAIL-VERIFICATION-PLAN.md](docs/EMAIL-VERIFICATION-PLAN.md).

## Login dan pendaftaran akun internal

Halaman `/login` menerima email dan kata sandi. `/register` tanpa undangan menampilkan petunjuk akses; form aktivasi muncul setelah membuka undangan valid. Token disimpan sebagai hash SHA-256, berlaku 48 jam dan hanya bisa dipakai sekali. Email/role ditentukan undangan di server; input role/email dari form tidak bisa menaikkan akses. Undangan baru membatalkan tautan lama untuk email tersebut.

Sesudah migration, buat admin pertama melalui terminal proyek:

```powershell
php artisan hr:create-admin
```

Perintah meminta nama, email dan kata sandi secara interaktif; kata sandi tidak ditampilkan. Bootstrap ditolak bila admin sudah ada. Tidak ada password default. Untuk menambah akun:

```powershell
php artisan hr:invite staf@example.test --role=hr_staff
```

Role yang diperbolehkan: `admin`, `hr_staff`, `viewer`. Salurkan tautan yang dihasilkan secara privat kepada penerima. Tidak ada email otomatis. Sesuaikan `APP_URL` dengan alamat aplikasi agar tautan mengarah ke host yang benar. Akses terminal/server setara dengan wewenang operator; UI pengelolaan undangan belum tersedia.

Login dibatasi 5 request per menit per kombinasi email/IP dan 20 per IP; aktivasi 10 per IP. Password baru minimal 12 karakter dengan huruf besar/kecil dan angka, maksimal 72 byte sesuai bcrypt. Session diregenerasi saat masuk, dibatalkan saat keluar; CSRF tetap aktif. Untuk production wajib HTTPS, APP_DEBUG=false, APP_ENV=production dan SESSION_SECURE_COOKIE=true; cookie secure juga default aktif pada APP_ENV production. Reset password, verifikasi email akun internal dan 2FA belum diaktifkan; bantuan akun masih melalui operator. Verifikasi email lamaran publik merupakan alur terpisah.


## Jadwal interview dan undangan

Buka Rekrutmen → Jadwal interview (`/interviews`), atau tombol Jadwalkan interview pada detail lamaran di tahap Interview. Admin/Staf HR memilih waktu WIB, durasi15–480menit, pewawancara dan lokasi/tautan HTTPS. Waktu disimpan UTC. Bentrok pewawancara maupun pelamar (termasuk lintas lamaran) ditolak; slot bersebelahan diperbolehkan.

Agenda memiliki tab Mendatang/Semua riwayat/Dibatalkan dan pagination15. Pembatalan memerlukan alasan dan menyimpan pelaku/waktu/versi. Untuk menjadwalkan ulang, batalkan jadwal lama lalu buat jadwal baru. Keluar tahap Interview otomatis membatalkan jadwal belum selesai. Viewer hanya melihat agenda dasar, tanpa lokasi/tautan/catatan/PDF.

Unduh undangan PDF dari kartu agenda. PDF memuat jadwal dan petunjuk konfirmasi ke HR, tanpa catatan internal, resource remote atau eksekusi PHP/JS dari HTML. Tidak ada pengiriman undangan otomatis. Jadwal batal tidak dapat menghasilkan undangan baru. Scope di docs/INTERVIEW-PLAN.md.
