# Walkthrough Wawancara 3 Menit — HRIS Lite

Dokumen ini panduan presentasi untuk wawancara magang. Semua data yang ditampilkan fiktif (domain `example.test`, nama dan perusahaan pura-pura). Aplikasi dijalankan lokal pada `http://127.0.0.1:8000` dengan database terpisah untuk development dan testing.

## 0:00 — Konteks proyek
- HRIS Lite adalah protopipe independen untuk demonstrasi portofolio, bukan sistem perusahaan asli.
- Stack: Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4 + Vite + MySQL.
- Bahasa antarmuka Indonesia; tanggal ditampilkan WIB, disimpan UTC.
- Role: `admin`, `hr_staff`, `viewer`. Akses ditetapkan server-side melalui policy, tidak hanya disembunyikan dari UI.

Buka halaman awal, jelaskan scope MVP: manajemen pegawai, lowongan, pelamar/lamaran, pipeline seleksi, interview, undangan PDF, dan CV privat.

## 0:20 — Dashboard (`/hr`)
- Masuk sebagai `admin` atau `hr_staff`.
- Tampilkan kartu ringkasan: jumlah pegawai aktif, lowongan terbuka, jumlah pelamar unik, jumlah lamaran per tahap seleksi.
- Jelaskan angka berasal dari query database aktual (tidak angka palsu), soft-deleted tidak terhitung.
- Klik pegawai terbaru atau lowongan terbaru untuk membuka detail.

## 0:45 — CRUD Pegawai (`/employees`)
- Daftar pegawai: pencarian nama/nomor/email, filter departemen/status, sorting whitelist, paginasi 15.
- Klik "Tambah Pegawai", isi formulir, simpan. Tunjukkan validasi server-side (nomor dan email unik, jabatan harus milik departemen yang sama).
- Buka detail pegawai, lihat riwayat event (arsip/restore) dengan actor dan waktu.
- Akses tab Arsip (hanya Admin): arsip dengan alasan 20–1000 karakter dan PDF pendukung maksimal 5 MiB, lalu pulihkan.
- Jelaskan: Viewer hanya baca, tidak bisa arsip/restore.

## 1:15 — Lowongan dan Kandidat (`/vacancies`, `/applications`)
- Buka `/vacancies`: daftar lowongan dengan pencarian, filter departemen/status, sorting, paginasi.
- Buat lowongan baru (draft → dibuka), jelaskan tanggal buka/tutup dan status.
- Buka `/applications` (Pipeline kandidat): board per tahap seleksi (Screening, Interview, Offering, Diterima, Ditolak) dan tabel daftar.
- Catat lamaran baru: pilih profil pelamar atau buat profil baru, pilih lowongan yang sedang dibuka, isi tanggal dan catatan HR, unggah CV PDF opsional.
- Jelaskan: satu profil dapat melamar banyak lowongan, tetapi pasangan profil/lowongan hanya sekali; duplikat ditolak database.

## 1:40 — Transisi Status dan Audit (`/applications/{id}`)
- Buka detail lamaran. Lihat timeline riwayat tahap dengan actor, waktu, dan catatan.
- Ubah tahap secara manual: Screening → Interview → Offering → Diterima/Ditolak.
- Coba loncat tahap (misal Screening langsung Diterima) — ditolak validasi server.
- Coba ubah dengan formulir lama (version tidak cocok) — ditolak 409.
- Jelaskan: riwayat append-only, actor diambil dari session (bukan payload), transaksi database untuk status + history agar tidak ada riwayat yang pecah setengah jalan.

## 2:10 — Interview, PDF undangan, dan CV (`/interviews`, CV)
- Buka `/interviews`: daftar jadwal dengan tab Mendatang/Semua/Dibatalkan.
- Jadwalkan interview untuk lamaran tahap Interview: waktu WIB (15–480 menit), lokasi atau tautan HTTPS, pewawancara.
- Coba bentrok waktu pewawancara yang sama — ditolak; slot bersebelahan diperbolehkan.
- Unduh undangan PDF dari kartu agenda. Tunjukkan berkas PDF privat (attachment, nosniff, no catatan internal, tidak dikirim email otomatis).
- Buka detail lamaran, unggah versi CV baru. Tunjukkan validasi PDF maksimal 5 MiB, file disimpan disk privat, unduh hanya Admin/Staf HR dengan pemeriksaan kepemilikan.

## 2:40 — Role, Tes, dan Batasan
- Masuk sebagai `viewer`: tampilkan bahwa tombol tambah/edit/arsip tidak muncul dan mutasi ditolak server (403).
- Masuk sebagai `admin`: seluruh aksi CRUD dan arsip tersedia.
- Jalankan `php artisan test` — tunjukkan 75 tes / 1004 assertions lolos, mencakup guest redirect, role enforcement, validasi CRUD, transisi status, riwayat, akses dokumen privat, dan PDF.
- Batasan yang jujur: belum ada manajemen master departemen/jabatan via UI (hanya seeder), belum edit/hapus profil pelamar via UI, belum email otomatis ke inbox (mailer log), belum dark mode, belum payroll/absensi, belum e-sign. Ini sengaja di luar scope MVP 5–7 hari.

## Catatan untuk pewawancara
- Tidak ada kredensial bawaan; admin pertama dibuat via `php artisan hr:create-admin`, akun lain via `php artisan hr:invite`.
- Semua file rahasia (`.env`, `.env.testing`, `.local/`, `.tools/`) diabaikan Git dan tidak di-commit.
- Detail teknis lengkap: `docs/PLAN.md`, `docs/ERD.md`, `docs/PROGRESS.md`.
