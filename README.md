# HRIS Lite

Aplikasi HRIS mandiri berbahasa Indonesia untuk tim HR: rekrutmen, direktori pegawai, kontrak, pelatihan, pelanggaran/SP, absensi, cuti dan izin, laporan, serta manajemen akun. Dibangun dengan Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Vite dan MySQL 8.4.

> **Status:** prototipe portofolio yang berjalan penuh di komputer lokal. Semua data pada demo fiktif (domain `example.test`). Belum ada UAT oleh tim HR dan belum ada hosting yang selalu aktif, jadi jangan diklaim siap production. Batas yang diketahui ada di bagian [Batasan](#batasan-yang-diketahui).

## Daftar isi

- [Fitur](#fitur)
- [Peran dan akses](#peran-dan-akses)
- [Menjalankan](#menjalankan)
- [Data demo dan akun lokal](#data-demo-dan-akun-lokal)
- [Pemeriksaan kualitas](#pemeriksaan-kualitas)
- [Keamanan dan keputusan desain](#keamanan-dan-keputusan-desain)
- [Staging](#staging)
- [Batasan yang diketahui](#batasan-yang-diketahui)
- [Dokumentasi](#dokumentasi)

## Fitur

| Modul | Rute | Ringkasan | Detail |
|---|---|---|---|
| Direktori pegawai | `/employees` | Cari, filter, sort, pagination 15. Tambah/edit oleh Admin dan Staf HR. Penghapusan berarti arsip (soft delete) dengan alasan, PDF pendukung dan konfirmasi nomor; hanya Admin yang mengarsipkan dan memulihkan. | [EMPLOYEE-PLAN](docs/EMPLOYEE-PLAN.md) |
| Impor pegawai | `/employees/import` | Unggah CSV atau XLSX, pratinjau dengan validasi per baris, lalu simpan semuanya atau tidak sama sekali. Ada template CSV. Lihat [aturan impor](#impor-pegawai). | |
| Departemen dan jabatan | `/departments`, `/positions` | Master data yang dipakai pegawai dan lowongan. | |
| Lowongan | `/vacancies` | Draft, dibuka, ditutup. Tampil di portal karier hanya dalam rentang tanggal WIB. | [VACANCY-PLAN](docs/VACANCY-PLAN.md) |
| Pelamar dan pipeline | `/applicants`, `/applications` | Screening, Interview, Offering, Diterima atau Ditolak. CV PDF privat, riwayat tahap dengan aktor dan catatan. | [APPLICATION-PLAN](docs/APPLICATION-PLAN.md) |
| Portal karier | `/karier` | Form lamaran tanpa akun, CV wajib, honeypot, throttle, verifikasi email per lamaran. | [CAREERS-PLAN](docs/CAREERS-PLAN.md), [EMAIL-VERIFICATION-PLAN](docs/EMAIL-VERIFICATION-PLAN.md) |
| Interview | `/interviews` | Jadwal WIB, bentrok pewawancara dan pelamar ditolak, undangan PDF, pembatalan dengan alasan. | [INTERVIEW-PLAN](docs/INTERVIEW-PLAN.md) |
| Penawaran dan konversi | detail lamaran | Penawaran, respons dengan bukti PDF, konversi pelamar diterima menjadi pegawai. | [OFFERING-PLAN](docs/OFFERING-PLAN.md) |
| Kontrak kerja | `/contracts` | PKWT/PKWTT, koreksi dengan alasan, PDF berversi, pembatalan tanpa menghapus riwayat, pengingat PKWT 30 hari. | [CONTRACT-PLAN](docs/CONTRACT-PLAN.md) |
| Pelatihan | `/trainings` | Jadwal, materi PDF berversi, peserta, kehadiran, pre-test dan post-test 0 sampai 100. | [TRAINING-PLAN](docs/TRAINING-PLAN.md) |
| Pelanggaran, SP dan konseling | `/discipline` | Catatan SP1 sampai SP3 dengan PDF wajib, tingkat dipilih HR, tanpa kenaikan otomatis. | [DISCIPLINE-PLAN](docs/DISCIPLINE-PLAN.md) |
| Absensi | `/attendance` | Kalender tahunan per pegawai, check-in/check-out web, jadwal kerja (satuan dan massal), pengajuan koreksi dengan alur atasan lalu HR, rekap bulanan dengan ekspor XLSX dan PDF. | [ATTENDANCE-PLAN](docs/ATTENDANCE-PLAN.md), [SCHEDULE](docs/ATTENDANCE-SCHEDULE-PLAN.md), [PUNCH](docs/ATTENDANCE-PUNCH-PLAN.md), [CORRECTION](docs/ATTENDANCE-CORRECTION-PLAN.md), [RECAP](docs/ATTENDANCE-RECAP-PLAN.md) |
| Cuti dan izin | `/leave` | Pengajuan hari penuh, saldo tahunan, hari libur kantor, persetujuan atasan lalu HR, bukti PDF opsional. | [LEAVE-PLAN](docs/LEAVE-PLAN.md) |
| Laporan HR | `/reports` | Pratinjau 10 baris, ekspor XLSX dan PDF untuk rekrutmen, evaluasi pelatihan, kontrak dan SP. | [REPORT-PLAN](docs/REPORT-PLAN.md) |
| Kelola akun | `/accounts` | Undangan sekali pakai, role, status, tautan pemulihan password, struktur atasan. Khusus Admin. | [ACCOUNT-MANAGEMENT-PLAN](docs/ACCOUNT-MANAGEMENT-PLAN.md) |
| Jejak audit | `/audit` | Satu halaman yang menggabungkan seluruh riwayat modul. Khusus Admin. Lihat [aturan audit](#jejak-audit). | |
| Portal pegawai dan atasan | `/me`, `/team` | Pegawai melihat data dan menu miliknya; atasan membaca tim langsung. | |

Antarmuka berbahasa Indonesia. Waktu disimpan dalam UTC dan ditampilkan dalam WIB.

### Impor pegawai

- Format CSV (pemisah `,` atau `;`, BOM dan Windows-1252 ditangani) atau XLSX sheet pertama. Maksimal 2 MB dan 500 baris.
- XLSX dibaca dengan ZipArchive dan SimpleXML bawaan PHP, tanpa dependency baru. Berkas dengan `DOCTYPE`/`ENTITY` ditolak, dan formula tidak dihitung (hanya nilai tersimpan yang dibaca).
- Judul kolom fleksibel (misalnya `Nomor Pegawai` atau `NIP`, `Email Kerja` atau `Email`). Unduh template CSV dari halaman impor untuk melihat kolom yang dipakai.
- Departemen dicocokkan dengan nama atau kode, jabatan harus berada pada departemen itu, status dengan nilai atau label. Format tanggal `Y-m-d`, `d/m/Y`, `d-m-Y` dan serial tanggal Excel.
- Nomor pegawai dan email kerja dicek terhadap isi berkas dan database, termasuk pegawai yang sudah diarsipkan.
- Pratinjau disimpan di cache selama 30 menit per pengguna. Simpan hanya berhasil bila semua baris valid dan dicek ulang saat itu; satu transaksi untuk semua baris. Setiap pegawai mendapat catatan audit dengan `source: import`.
- Siapa yang boleh: pengguna yang berhak menambah pegawai (Admin dan Staf HR). Pratinjau dan simpan dibatasi 10 permintaan per menit.

### Jejak audit

Aplikasi tidak memakai satu tabel audit pusat. Setiap modul punya tabel peristiwa sendiri (aktor, aksi, perubahan, waktu). Halaman `/audit` menggabungkan sepuluh sumber lewat `UNION ALL`: pegawai, lamaran, penawaran, pelatihan, kontrak, disiplin, akun, cuti, absensi dan koreksi absensi.

- Filter modul, aksi dan rentang tanggal (WIB), 25 baris per halaman, urut terbaru.
- Yang ditampilkan: modul, aksi, pelaku (atau "Sistem"), nomor catatan dengan tautan, waktu. Isi perubahan, alasan dan catatan tidak ditampilkan di halaman ini; lihat riwayat pada modul masing-masing.
- Khusus Admin (gate `manage-accounts`).

## Peran dan akses

Lima role: **Admin**, **Staf HR**, **Pembaca** (`viewer`), **Pegawai** (`employee`) dan **Atasan** (`supervisor`). Otorisasi ditegakkan di server lewat gate dan policy, bukan hanya dengan menyembunyikan tombol.

| Role | Cakupan |
|---|---|
| Admin | Semua modul HR, kelola akun, jejak audit, arsip dan pemulihan pegawai, unduh dokumen arsip. |
| Staf HR | Modul HR: pegawai, impor, rekrutmen, kontrak, pelatihan, disiplin, laporan, absensi dan keputusan akhir cuti/koreksi. Tanpa kelola akun, audit dan arsip pegawai. |
| Pembaca | Hanya baca data dasar. Tanpa laporan, kasus disiplin, CV, dokumen dan catatan privat. |
| Pegawai | Portal `/me`, absensi sendiri, cuti dan koreksi sendiri. Wajib terhubung unik ke pegawai aktif. |
| Atasan | Seperti Pegawai, ditambah `/team` dan peninjauan cuti/koreksi anggota langsung. Tanpa akses modul HR. |

Akun nonaktif dan portal untuk pegawai yang diarsipkan atau nonaktif ditolak di backend.

## Menjalankan

### Prasyarat

- PHP 8.3.36 atau patch lebih baru, dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `dom`, `xml`, `xmlwriter`, `curl`, `zip`, `ctype`, `tokenizer`.
- Node.js 24 LTS dan npm. Composer 2 (tersedia lokal lewat `php .tools/composer.phar`).
- MySQL 8.4 LTS. Mesin pengembangan ini memakai binary Laragon 8.4.3 untuk demo loopback; itu bukan klaim patch MySQL terbaru.

### Checkout yang sudah disiapkan

Dari root proyek di PowerShell:

```powershell
.\scripts\start-database.ps1
php artisan migrate:status
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000>. Untuk mengedit frontend, jalankan `npm run dev` di terminal kedua (Vite di `127.0.0.1`, port bawaan 5173). Setelah perubahan, `npm run build` menghasilkan aset statis.

MySQL proyek berjalan di `127.0.0.1:3307` dengan data di `.local/mysql-data` dan lokasi binary di `.local/runtime.json`. Setelah Windows restart, jalankan `start-database.ps1` lagi; tidak ada service atau autostart baru. Untuk mematikan dengan aman, pakai `mysqladmin.exe --host=127.0.0.1 --port=3307 --user=root --password shutdown`, jangan hentikan prosesnya paksa.

Password acak hanya tersimpan di `.env`, `.env.testing` dan `.local/database-credentials.json`, semuanya diabaikan Git. Jangan diunggah atau dibagikan.

### Setup dari checkout baru

```powershell
.\scripts\install-composer.ps1
php .tools/composer.phar install
npm ci
Copy-Item .env.example .env
Copy-Item .env.testing.example .env.testing
```

Siapkan MySQL lokal yang sudah diinisialisasi, lalu jalankan SQL berikut sebagai administrator database. Ganti placeholder dengan dua password acak yang berbeda:

```sql
CREATE DATABASE hris_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE hris_lite_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hris_dev'@'localhost' IDENTIFIED BY '<PASSWORD_DEV_ACAK>';
CREATE USER 'hris_test'@'localhost' IDENTIFIED BY '<PASSWORD_TEST_ACAK>';
GRANT ALL PRIVILEGES ON hris_lite.* TO 'hris_dev'@'localhost';
GRANT ALL PRIVILEGES ON hris_lite_test.* TO 'hris_test'@'localhost';
```

Isi password pada `.env` dan `.env.testing`, sesuaikan port bila perlu (template memakai 3307), lalu:

```powershell
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
php artisan migrate --env=testing
php artisan db:seed --class=EmployeeMasterSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

`start-database.ps1` hanya untuk direktori data proyek ini. Pakai service MySQL sendiri bila setup Anda berbeda, dan jangan menginisialisasi ulang direktori data yang sudah ada.

`APP_DEBUG` default `false`; aktifkan sementara hanya untuk debugging lokal. `MAIL_MAILER=log` untuk pengembangan, jadi email pelamar ditulis ke log dan bukan ke inbox.

### Membuat akun pertama

```powershell
php artisan hr:create-admin
php artisan hr:invite staf@example.test --role=hr_staff
```

`hr:create-admin` meminta nama, email dan password secara interaktif, dan ditolak bila admin sudah ada. `hr:invite` menerbitkan tautan undangan; salurkan secara privat karena tidak ada email otomatis. Command ini menerima role `admin`, `hr_staff` dan `viewer`; role Pegawai dan Atasan serta pemetaan ke pegawai diatur Admin lewat UI `/accounts`. Sesuaikan `APP_URL` agar tautan mengarah ke host yang benar.

### Antrean email pelamar

```powershell
php artisan queue:work recruitment --queue=recruitment-mail --tries=3 --timeout=60
```

Koneksi `recruitment` memakai database aplikasi yang sama, sehingga pekerjaan, lamaran dan metadata berkas masuk satu transaksi. Untuk SMTP sungguhan, isi `MAIL_*`, alamat pengirim dan `APP_URL` (HTTPS di production), lalu restart worker. Periksa `failed_jobs` bila pengiriman gagal; retry dapat mengirim email dua kali.

## Data demo dan akun lokal

Di environment `local` dan `testing`, seeder mengisi data fiktif yang lengkap dan aman dijalankan ulang (memakai `firstOrCreate` dan kunci unik). Di production seeder demo dilewati.

```powershell
php artisan db:seed --force
```

| Akun | Email | Role |
|---|---|---|
| Admin | `admin@example.test` | `admin` |
| Staf HR | `hr@example.test` | `hr_staff` |
| Pembaca | `viewer@example.test` | `viewer` |
| Atasan | `atasan@example.test` | `supervisor` |
| Pegawai | `pegawai@example.test` | `employee` |

Password default lokal `DemoHris123!`, dapat diganti lewat `DEMO_PASSWORD` sebelum seeding. Seeding menyetel ulang password kelima akun agar dokumentasi selalu berlaku. Akun ini hanya untuk local dan testing, dan tidak boleh dipakai di production. Isi data contoh dan alur presentasi ada di [DEMO.md](docs/DEMO.md).

Pratinjau tanpa login tersedia di `/preview/hr` (hanya di environment local, 404 di production). Datanya fiktif dari `resources/js/data/hr-preview.ts` dan tidak disimpan.

## Pemeriksaan kualitas

```powershell
php artisan test
php vendor/bin/pint --test
npm run lint
npm run format:check
npm run types:check
npm run build
```

Untuk validasi dependensi: `php .tools/composer.phar validate --strict` dan `php .tools/composer.phar check-platform-reqs`. Perbaikan format: `php vendor/bin/pint` dan `npm run format`.

- Tes memakai `.env.testing` dan akun `hris_test` pada database `hris_lite_test`. Jalankan migration test lebih dulu, dan pastikan MySQL proyek menyala sebelum `php artisan test`.
- Suite membuktikan koneksi MySQL dan bahwa akun test tidak bisa membaca database development.
- Build diperlukan karena smoke test HTTP membaca manifest Vite.
- Hasil lengkap terakhir yang tercatat di [STAGING.md](docs/STAGING.md): 185 tes dan 2.785 assertion lulus pada 3 November 2026. Tes impor pegawai dan jejak audit ditambahkan sesudahnya, jadi angka itu perlu dihitung ulang.
- Pada sandbox Windows tertentu, akses socket localhost atau file PHP/Pint bisa ditolak meskipun terminal biasa berhasil. Gunakan izin eksekusi yang sesuai; jangan melonggarkan validasi aplikasi untuk melewatinya.

## Keamanan dan keputusan desain

- **Autentikasi.** Login lewat Fortify dibatasi 5 permintaan per menit per kombinasi email dan IP, serta 20 per IP. Password baru minimal 12 karakter dengan huruf besar, kecil dan angka, maksimal 72 byte (batas bcrypt). Session diregenerasi saat masuk dan dibatalkan saat keluar, CSRF aktif.
- **Akun.** Tidak ada kredensial bawaan. Pendaftaran hanya lewat undangan sekali pakai (token disimpan sebagai hash SHA-256, berlaku 48 jam). Perubahan akses mencabut tautan reset lama, merotasi remember token dan mencabut sesi lama. Mutasi akun meminta password Admin, dan perubahan sensitif disertai alasan.
- **Berkas privat.** PDF (CV, kontrak, SP, materi, bukti) disimpan di disk privat, tidak lewat URL publik. Diperiksa ekstensi, MIME dan header, maksimal 5 MiB, diunduh sebagai attachment `nosniff` setelah otorisasi, dan dibersihkan bila transaksi database gagal. Belum ada antivirus atau sanitasi PDF, jadi lampiran diperlakukan sebagai tidak tepercaya.
- **Konkurensi.** Form memakai versi dan row lock. Formulir lama atau tab basi ditolak dan tidak menimpa perubahan pengguna lain. Jadwal interview dan absensi diserialisasi dengan lock agar tidak bentrok.
- **Audit.** Mutasi penting menyimpan aktor dari session, waktu UTC, alasan dan perubahan sebelum/sesudah dalam transaksi yang sama dengan datanya.
- **Tanpa penghapusan permanen.** Pegawai, kontrak, pelatihan dan kasus disiplin diarsipkan atau dibatalkan dengan alasan; tidak ada endpoint pemusnahan.
- **Production.** Wajib HTTPS, `APP_ENV=production`, `APP_DEBUG=false` dan `SESSION_SECURE_COOKIE=true` (cookie secure sudah aktif secara default di production).

Pedoman visual, token warna dan larangan desain generik ada di [DESIGN.md](DESIGN.md). Route `/lab` menampilkan komponen impor sebagai spesimen dan sengaja tidak mengikuti aturan itu.

## Staging

Instance staging berjalan di komputer lokal pada `127.0.0.1:8002`, dengan database, session, cache, antrean, upload dan log terpisah dari development dan test, lalu dipublikasikan lewat Cloudflare Tunnel. Cara menyalakan semua prosesnya ada di [STAGING.md](docs/STAGING.md). Karena berjalan di PC lokal, staging belum menjadi layanan hosting yang selalu aktif. Jangan menjalankan `migrate:fresh` pada database apa pun yang berisi data pengguna.

## Batasan yang diketahui

- Pengiriman email memakai mailer `log`. Verifikasi pelamar dan tautan reset belum terkirim ke penerima sungguhan.
- Belum ada CAPTCHA, antivirus berkas, 2FA, atau verifikasi email untuk akun internal.
- Absensi web tidak memverifikasi lokasi. Tidak ada fingerprint, impor mesin absensi, payroll, lembur atau SP otomatis.
- Cuti hanya hari penuh dengan kalender Senin sampai Jumat; belum ada shift, setengah hari, akrual atau carry-over.
- Kebijakan retensi, pemusnahan permanen dan persetujuan dua orang untuk arsip belum ditentukan, dan itu keputusan perusahaan, bukan klaim kewajiban hukum.
- Pembaca masih dapat melihat email dan telepon kerja pegawai serta email pelamar. Pembatasan kolom untuk role ini belum diputuskan.
- Halaman audit menampilkan nomor catatan disiplin dan SP kepada Admin; sebagian peristiwa (cuti, absensi, penawaran) belum punya tautan ke catatannya.
- Impor pegawai dibatasi 500 baris per berkas dan hanya membuat pegawai baru, belum memperbarui yang sudah ada.
- UAT dengan tim HR sungguhan masih diperlukan sebelum dipakai untuk data nyata.

## Dokumentasi

- Pedoman kerja repositori dan agen: [AGENTS.md](AGENTS.md)
- Brief, rencana dan skema: [BRIEF](docs/BRIEF.md), [PLAN](docs/PLAN.md), [ERD](docs/ERD.md)
- Catatan pengerjaan dan hasil verifikasi per fitur: [PROGRESS](docs/PROGRESS.md)
- Presentasi dan demo: [DEMO](docs/DEMO.md), [SCREENSHOTS](docs/SCREENSHOTS.md), [SCREENSHOTS-CRUD](docs/SCREENSHOTS-CRUD.md)
- Pengujian pengguna: [RECRUITMENT-UAT](docs/RECRUITMENT-UAT.md), [UAT-CRUD](docs/UAT-CRUD.md), [VOICEOVER-UAT](docs/VOICEOVER-UAT.md)
- Staging dan tunnel: [STAGING](docs/STAGING.md), [STAGING-TUNNEL-PLAN](docs/STAGING-TUNNEL-PLAN.md)
- Autentikasi dan tinjauan desain: [AUTH-PLAN](docs/AUTH-PLAN.md), [TASTE-REVIEW](docs/TASTE-REVIEW.md)
- Rencana per modul: lihat kolom Detail pada tabel [Fitur](#fitur)
