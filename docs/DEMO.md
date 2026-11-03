# Walkthrough Wawancara — HRIS Lite

Panduan presentasi singkat (3 menit) dan demo lanjutan (5–7 menit). Semua data yang ditampilkan fiktif: domain `example.test`, nama, perusahaan, dan berkas contoh. Aplikasi berjalan lokal di `http://127.0.0.1:8000` dengan database development terpisah dari database testing.

## Persiapan

```powershell
.\scripts\start-database.ps1
php artisan migrate
php artisan db:seed --force
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Seeder demo hanya berjalan pada environment `local`/`testing`, idempotent, dan membuat data contoh lengkap: 25 pegawai, 6 lowongan, 30 lamaran, 8 wawancara, 8 kontrak, 3 pelatihan, 3 kasus disiplin, 14 penawaran, saldo cuti 12 hari untuk pegawai aktif, 5 pengajuan cuti/izin, 3 hari libur, dan catatan kehadiran ±50 hari kerja untuk tim demo. Akun demo:

| Role | Email | Kata sandi |
|---|---|---|
| Admin | `admin@example.test` | `DemoHris123!` |
| Staf HR | `hr@example.test` | `DemoHris123!` |
| Pembaca | `viewer@example.test` | `DemoHris123!` |
| Atasan | `atasan@example.test` | `DemoHris123!` |
| Pegawai | `pegawai@example.test` | `DemoHris123!` |

Kata sandi dapat diganti lewat `DEMO_PASSWORD` sebelum seeding. Seeding menyetel ulang kata sandi kelima akun demo. Akun ini hanya untuk local/testing, bukan production. Akun `atasan@example.test` terhubung ke Andi Pratama, sedangkan `pegawai@example.test` terhubung ke Budi Saputra yang melapor langsung kepada Andi.

## Alur 3 menit

### 0:00 — Konteks

- HRIS Lite adalah prototipe portofolio independen, bukan sistem perusahaan asli.
- Stack: Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Vite, MySQL.
- Bahasa antarmuka Indonesia; tanggal ditampilkan WIB, disimpan UTC.
- Lima role: `admin`, `hr_staff`, `viewer`, `employee`, `supervisor`. Otorisasi ditegakkan server-side melalui gate/policy, bukan hanya menyembunyikan tombol.

### 0:20 — Dashboard (`/hr`)

- Tunjukkan kartu pegawai, lowongan aktif, pelamar, tahap interview, dan **wawancara mendatang** yang diambil dari tabel `interviews`.
- Tunjukkan pengingat PKWT 30 hari dan kontrak yang sudah berakhir.
- Buka pipeline kandidat dan lamaran terbaru.

### 0:50 — Pegawai dan master data (`/employees`, `/departments`, `/positions`)

- Cari/filter pegawai; buka detail dan riwayat audit.
- Tunjukkan master departemen dan jabatan yang sudah bisa dikelola dari UI.
- Jelaskan validasi: nomor/email unik, jabatan harus satu departemen, form lama ditolak lewat versi.

### 1:20 — Rekrutmen (`/vacancies`, `/applications`)

- Buat/tunjukkan lowongan draft dan published; jelaskan periode WIB.
- Buka board pipeline: Screening → Interview → Offering → Diterima/Ditolak.
- Tunjukkan detail lamaran: timeline riwayat, aktor, catatan, dan CV privat.
- Jelaskan satu profil boleh melamar banyak lowongan, tetapi pasangan profil/lowongan unik.

### 1:50 — Interview, PDF, dan CV (`/interviews`)

- Buka agenda interview; tunjukkan tab mendatang/semua/dibatalkan.
- Unduh undangan PDF; jelaskan berkas privat, tanpa catatan internal, dan tidak dikirim email otomatis.
- Tunjukkan validasi bentrok jadwal dan pembatalan yang menyimpan riwayat.

### 2:10 — Modul hubungan kerja (pilih satu)

Pilih satu atau dua sesuai waktu:

- **Kontrak** (`/contracts`): PKWT/PKWTT, pengingat 30 hari, koreksi beralasan, PDF privat.
- **Pelatihan** (`/trainings`): peserta, kehadiran, pre/post-test, ringkasan selisih poin.
- **Pelanggaran/SP** (`/discipline`): SP1–3/konseling, tindak lanjut, audit.
- **Laporan** (`/reports`): pratinjau dan ekspor XLSX/PDF untuk rekrutmen, pelatihan, kontrak, dan SP.

### 2:35 — Cuti, izin, dan kehadiran (`/leave`, `/attendance`)

- Tunjukkan saldo cuti tahunan, pengajuan contoh, riwayat, dan alur persetujuan atasan → HR.
- Buka kalender kehadiran: status tepat waktu/terlambat, cuti disetujui, dan detail tanggal.
- Tunjukkan jadwal kerja massal (`/attendance/schedules`) untuk membuat jadwal beberapa pegawai sekaligus.

### 2:55 — Role, keamanan, dan tes

- Masuk sebagai `viewer`: tombol mutasi tidak muncul dan request mutasi ditolak 403.
- Masuk sebagai `employee`/`supervisor`: portal `/me`, `/team`, `/leave`, dan `/attendance` hanya menampilkan data yang diizinkan; modul HR tetap ditolak.
- Halaman galat 403/404/500 tampil dalam bahasa Indonesia.
- Jalankan `php artisan test`: **155 tes / 2.361 assertions**, mencakup guest redirect, role enforcement, validasi, transisi status, audit, dokumen privat, PDF, cuti, kehadiran, jadwal massal, dan rollback file.
- Sebutkan batasan jujur: mailer lokal masih log, belum ada CAPTCHA/antivirus, belum ada payroll/check-in mandiri/impor mesin absensi, belum ada 2FA/verifikasi email akun internal, dan UAT tim HR masih diperlukan.

## Demo lanjutan 5–7 menit

1. **Offering dan konversi** (`/applications/{id}/offering`): revisi penawaran, respons dengan bukti PDF, konversi kandidat diterima menjadi pegawai tanpa menimpa profil pelamar.
2. **Manajemen akun** (`/accounts`): undangan sekali pakai, role/status, reset password, struktur atasan, dan audit.
3. **Portal pegawai** (`/me`) dan **tim atasan** (`/team`): pembatasan data dasar dan penolakan akses lintas tim.
4. **Pelatihan**: nilai 0 tetap valid, post-test memerlukan pre-test dan kehadiran, nilai dapat dikoreksi setelah sesi selesai.
5. **Pelanggaran/SP**: koreksi wajib alasan dan versi; pembatalan administratif tidak menghapus audit.
6. **Laporan**: filter maksimal 366 hari, batas ekspor 1.000 baris XLSX/200 baris PDF, tanpa data kontak/kasus privat.
7. **Karier publik** (`/karier`): form tanpa akun, verifikasi email per lamaran, dan pipeline HR yang menunggu verifikasi.

## Batasan yang perlu disebutkan

- Tidak ada kredensial production bawaan; akun demo hanya dibuat di local/testing.
- Email verifikasi/notifikasi memakai mailer `log`; inbox nyata memerlukan SMTP dan worker antrean.
- Belum ada antivirus/sanitasi PDF; lampiran diperlakukan sebagai dokumen tidak tepercaya.
- Profil pelamar belum bisa diedit/dihapus dari UI; lowongan ditutup, bukan dihapus.
- Belum ada payroll, check-in/out mandiri, impor mesin absensi, e-sign, template surat resmi, atau 2FA.
- Verifikasi browser dan UAT HR untuk modul baru masih perlu dilakukan sebelum klaim produksi.

## Catatan untuk pewawancara

- Detail teknis: [PLAN.md](PLAN.md), [ERD.md](ERD.md), [PROGRESS.md](PROGRESS.md).
- Checklist rekrutmen: [RECRUITMENT-UAT.md](RECRUITMENT-UAT.md).
- Daftar halaman screenshot: [SCREENSHOTS.md](SCREENSHOTS.md).
- File rahasia `.env`, `.env.testing`, `.local/`, dan `.tools/` diabaikan Git.
