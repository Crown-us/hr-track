# Panduan Screenshot — HRIS Lite

Dokumen ini untuk ambil screenshot dokumentasi dan presentasi. Semua screenshot harus menggunakan **fixtur fiktif saja**, tidak ada data nyata, tidak ada env/password, dan viewport konsisten.

## Halaman yang perlu di-screenshot

| No | Halaman | Keterangan |
|----|---------|------------|
| 1 | `/login` | Form masuk, error login salah, tampilan desktop |
| 2 | `/karier` | Landing publik, maksimal 3 lowongan aktif, tombol lamar |
| 3 | `/hr` | Dashboard HR, kartu ringkasan pegawai/lowongan/pelamar/pipeline |
| 4 | `/employees` | Tabel pegawai, pencarian, filter departemen/status, tombol tambah |
| 5 | `/employees/{id}` | Detail pegawai + riwayat event |
| 6 | `/vacancies` | Daftar lowongan, filter, status badge |
| 7 | `/applications` | Pipeline kandidat board per tahap + tabel daftar |
| 8 | `/applications/{id}` | Detail lamaran, timeline riwayat, status badge |
| 9 | `/applicants` | Daftar profil pelamar (baru) |
| 10 | `/applicants/{id}` | Detail profil pelamar, daftar lamaran, CV |
| 11 | `/interviews` | Agenda jadwal interview, tab Mendatang/Semua/Dibatalkan |
| 12 | Undangan PDF | Contoh cetak PDF undangan interview (satu halaman A4) |

## Persiapan viewport
- Desktop: 1440 × 900 (atau setidaknya 1280 × 720), konsisten semua halaman.
- Mobile: 390 × 844, cek tidak ada overflow horizontal (`documentWidth ≤ 390`).
- Jangan buka DevTools saat ambil screenshot agar konsol tidak ikut terbawa.
- Jangan tampilkan `.env`, `.env.testing`, `.local/`, atau `.tools/`.

## Isi yang diizinkan vs dilarang

Diizinkan:
- Nama, email, telepon, alamat fiktif (domain `example.test`).
- Judul lowongan, departemen, jabatan pura-pura.
- Tanggal dan waktu contoh (WIB).
- Status seleksi: Screening, Interview, Offering, Diterima, Ditolak.

Dilarang:
- Data karyawan, pelamar, atau pasien nyata.
- Kata sandi, token, API key, atau kredensial apa pun.
- Path file sistem, string koneksi database, atau nama host produksi.
- Label "demo/prototipe/fiktif" berulang di UI (disclaimer disiapkan terpisah pengguna).

## Cara ambil
1. Nyalakan MySQL development dan jalankan `php artisan serve`.
2. Login sebagai `admin` atau `hr_staff`.
3. Buka halaman sesuai tabel di atas, ambil screenshot desktop dan mobile.
4. Simpan ke folder yang ditentukan pengguna (contoh: `.local/screenshots/`), jangan commit ke Git.
5. Hapus data uji QA setelah selesai, jangan reset database development pengguna.

## Catatan
- Screenshot hanya untuk dokumentasi presentasi, bukan klaim layanan produksi.
- Referensi detail fitur: `docs/PLAN.md`, `docs/APPLICATION-PLAN.md`, `docs/INTERVIEW-PLAN.md`, `docs/RECRUITMENT-UAT.md`.
