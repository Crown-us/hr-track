# Panduan Screenshot — HRIS Lite

Dokumen ini untuk mengambil screenshot dokumentasi dan presentasi. Gunakan **data demo fiktif** dari `php artisan db:seed --force` pada environment local/testing. Jangan menampilkan data nyata, `.env`, password, token, atau path server.

## Halaman yang perlu di-screenshot

| No | Halaman | Keterangan |
|---:|---|---|
| 1 | `/login` | Form masuk, error login salah, tampilan desktop/mobile |
| 2 | `/karier` | Landing publik, maksimal 3 lowongan aktif |
| 3 | `/karier/{id}` | Detail lowongan publik dan tombol lamar |
| 4 | `/karier/{id}/lamar` | Form lamaran tanpa akun, CV, dan persetujuan |
| 5 | `/hr` | Dashboard: metrik, wawancara mendatang, kontrak, pipeline |
| 6 | `/employees` | Direktori pegawai, pencarian, filter, tombol tambah |
| 7 | `/employees/{id}` | Detail pegawai dan riwayat audit |
| 8 | `/departments` | Master departemen dan form tambah/edit |
| 9 | `/positions` | Master jabatan dan relasi departemen |
| 10 | `/vacancies` | Daftar lowongan, filter, status badge |
| 11 | `/applications` | Board pipeline dan tabel lamaran |
| 12 | `/applications/{id}` | Timeline lamaran, catatan, CV, aksi tahap |
| 13 | `/applications/{id}/offering` | Penawaran, respons, dan konversi pegawai |
| 14 | `/applicants` | Direktori profil pelamar |
| 15 | `/applicants/{id}` | Profil pelamar dan daftar lamaran |
| 16 | `/interviews` | Agenda interview, tab mendatang/semua/dibatalkan |
| 17 | `/contracts` | Daftar kontrak dan pengingat PKWT |
| 18 | `/trainings` | Daftar pelatihan dan ringkasan peserta |
| 19 | `/trainings/{id}` | Detail pelatihan, peserta, evaluasi |
| 20 | `/discipline` | Daftar pelanggaran/SP/konseling |
| 21 | `/discipline/{id}` | Detail kasus, dokumen, dan audit |
| 22 | `/reports` | Pratinjau laporan dan tombol ekspor XLSX/PDF |
| 23 | `/accounts` | Manajemen akun, undangan, role/status |
| 24 | `/accounts/reporting` | Struktur atasan langsung |
| 25 | `/me` | Portal pegawai (role employee) |
| 26 | `/team` | Tim langsung (role supervisor) |
| 27 | PDF undangan interview | Satu halaman A4, data fiktif |
| 28 | PDF ringkasan penawaran | Ringkasan internal, bukan surat resmi |
| 29 | XLSX/PDF laporan | Contoh ekspor dengan filter periode |

## Persiapan viewport

- Desktop: 1440 × 900 atau minimal 1280 × 720, konsisten untuk semua halaman.
- Tablet: 768 × 1024.
- Mobile: 390 × 844; pastikan `documentWidth <= 390` dan tidak ada overflow horizontal.
- Jangan membuka DevTools saat screenshot agar console tidak ikut terlihat.
- Gunakan akun demo: admin untuk modul HR, viewer untuk tampilan baca, employee/supervisor untuk portal.

## Isi yang diizinkan vs dilarang

Diizinkan:

- Nama, email, telepon, alamat fiktif domain `example.test`.
- Judul lowongan, departemen, jabatan, dan catatan demo.
- Tanggal/waktu contoh dalam WIB.
- Status: Screening, Interview, Offering, Diterima, Ditolak.

Dilarang:

- Data karyawan, pelamar, atau pasien nyata.
- Kata sandi, token, API key, cookie, atau kredensial.
- Path file sistem, string koneksi database, atau host production.
- Menampilkan `.env`, `.env.testing`, `.local/`, atau `.tools/`.

## Cara mengambil

1. Jalankan MySQL development dan `php artisan serve`.
2. Jalankan `php artisan db:seed --force` bila data demo belum ada.
3. Login sebagai akun demo sesuai halaman.
4. Ambil screenshot desktop, tablet, dan mobile untuk halaman utama.
5. Simpan ke folder yang tidak di-commit, misalnya `.local/screenshots/`.
6. Hapus data QA sementara setelah selesai; jangan reset database development pengguna.

## Catatan

- Screenshot hanya untuk dokumentasi presentasi, bukan klaim layanan produksi.
- Referensi fitur: [PLAN.md](PLAN.md), [DEMO.md](DEMO.md), [RECRUITMENT-UAT.md](RECRUITMENT-UAT.md).
- Untuk PDF, gunakan data demo dan pastikan tidak ada catatan internal yang ikut tampil.
