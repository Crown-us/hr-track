# UAT CRUD menyeluruh — 3 November 2026

Dijalankan di instance staging lokal `http://127.0.0.1:8002` dengan database staging terpisah (`hris_lite_staging`, MySQL port 3307). Interaksi dilakukan lewat UI asli di Chrome headless (CDP): klik, isi form, unggah PDF, submit, lalu diverifikasi dari halaman hasil (flash, URL, dan isi tabel). Bukan pemanggilan API langsung.

- **Hasil: 29/29 langkah PASS** (durasi eksekusi 6 menit 12 detik).
- Rekaman layar: `.local/presentation/hris-lite-crud-uat.mp4` (6 menit 27 detik, 25,5 MB) — kamera punch-in ke field/tombol yang sedang dipakai, tempo diperlambat agar mudah diikuti.
- Alur disusun urut mengikuti siklus HR: master data → pegawai → rekrutmen → kontrak → pelatihan → disiplin → akun → absensi → cuti (pegawai, atasan, HR) → laporan.
- Screenshot per langkah: `.local/uat/shots/`.
- Data hasil ekspor: `.local/uat/artefacts/` (dua workbook XLSX asli).
- Database staging dipulihkan dari snapshot setelah UAT; tidak ada fixture QA yang tertinggal (42 tabel, jumlah baris identik dengan baseline).

## Hasil per langkah

| No | ID | Modul | Aksi (CRUD) | Hasil |
|----|----|-------|-------------|-------|
| 1 | M01 | Auth | Login admin | PASS |
| 2 | M02 | Departemen | Create (kode + nama) | PASS |
| 3 | M03 | Departemen | Update (ganti nama) | PASS |
| 4 | M04 | Jabatan | Create (nama + departemen) | PASS |
| 5 | M05 | Pegawai | Create (nomor, nama, email, telepon, tanggal, dept, jabatan) | PASS |
| 6 | M06 | Pegawai | Update (telepon) | PASS |
| 7 | M07 | Pegawai | Read (cari di direktori, hasil 1 baris) | PASS |
| 8 | R01 | Lowongan | Create (judul, dept, jenis, lokasi, deskripsi, syarat, tanggal, status published) | PASS |
| 9 | R02 | Karier publik | Read (lowongan baru tampil di `/karier`) | PASS |
| 10 | R03 | Pelamar | Create profil (nama, email, telepon) | PASS |
| 11 | R04 | Lamaran | Create + unggah CV PDF | PASS |
| 12 | R05 | Lamaran | Update tahap screening → interview | PASS |
| 13 | R06 | Interview | Create jadwal (lamaran, pewawancara, waktu, durasi, lokasi) | PASS |
| 14 | R07 | Kontrak | Create PKWT (nomor, tanggal mulai/berakhir) | PASS |
| 15 | R08 | Kontrak | Upload PDF berkas kontrak | PASS |
| 16 | R09 | Pelatihan | Create jadwal (judul, pemateri, lokasi, mulai, selesai) | PASS |
| 17 | R10 | Pelatihan | Create peserta | PASS |
| 18 | R11 | Disiplin | Create catatan (pegawai, nomor, jenis, tanggal, subjek, deskripsi) | PASS |
| 19 | A01 | Akun | Create undangan (email, role, kata sandi admin) | PASS |
| 20 | A02 | Akun | Delete undangan (cabut + alasan + kata sandi admin) | PASS |
| 21 | H01 | Absensi (HR) | Update catatan hari ini (jadwal, alasan, konfirmasi) | PASS |
| 22 | E00 | Auth | Ganti sesi ke akun pegawai | PASS |
| 23 | E01 | Absensi (pegawai) | Create catat masuk + catat pulang | PASS |
| 24 | E02 | Cuti | Create pengajuan (jenis, tanggal, alasan) | PASS |
| 25 | S00 | Auth | Ganti sesi ke atasan | PASS |
| 26 | S01 | Cuti | Update keputusan atasan (setujui) | PASS |
| 27 | H02 | Cuti | Update keputusan akhir HR (setujui) | PASS |
| 28 | L01 | Laporan | Read filter periode + ekspor XLSX | PASS |
| 29 | L02 | Rekap absensi | Read filter bulan + ekspor XLSX | PASS |

## Bukti ekspor

Dua berkas benar-benar terunduh dan diperiksa sebagai arsip OOXML valid (6 entri, diawali `[Content_Types].xml`):

- `laporan-recruitment-2026-09-01-2026-11-03.xlsx` (3,1 KB)
- `rekap-absensi-2026-11.xlsx` (4,0 KB)

## Zoom & tempo rekaman

- Setiap isian field dan klik tombol memicu punch-in ~1,3–1,4× ke elemen tersebut (animasi masuk 0,7 detik, keluar 0,6 detik), lalu kamera kembali ke 1× sebelum aksi berikutnya.
- Kecepatan mengetik 66–70 ms per karakter (semula 2–14 ms, lalu 42–45 ms), plus jeda 0,2–0,6 detik di sekitar zoom dan 0,3 detik antar langkah. Durasi rekaman naik bertahap: 2:34 → 4:34 → 6:27.

## Cakupan yang belum dijalankan di UAT ini

Dijalankan lewat test suite otomatis (185 tes / 2.785 asersi), belum diklik manual pada UAT ini:

- Penawaran kerja (simpan penawaran, respons kandidat + bukti, konversi ke pegawai).
- Lamaran publik `/karier/{id}/lamar` sampai verifikasi email bertanda tangan (butuh kotak surat; mailer log).
- Jadwal massal absensi (pratinjau + commit token).
- Pengajuan koreksi absensi oleh pegawai dan keputusan HR.
- Pengaturan cuti (alokasi saldo, tambah/hapus hari libur) dan pembatalan cuti.
- Evaluasi pelatihan, unggah materi, ubah status pelatihan.
- Koreksi/void kontrak, ubah status catatan disiplin.
- Arsip/pulihkan pegawai dan unggah dokumen arsip.
- Ubah akses akun, tautan reset kata sandi, penetapan atasan langsung.
- Registrasi undangan dan reset kata sandi.
- Uji negatif otorisasi (viewer/guest) — sudah dicakup test suite.

## Catatan teknis

- Driver UAT: `.local/uat/uat.mjs` (sesi CDP + helper label/validasi) dan `.local/uat/plan.mjs` (daftar langkah).
- Snapshot/restore DB: `.local/uat/snapshot.php` (JSON + base64, 42 tabel).
- Verifikasi kode sebelum UAT: `php artisan test` 185 lulus / 2.785 asersi, Pint, ESLint, Prettier, `tsc --noEmit`, dan `npm run build` semuanya lulus.
- Tidak ada perubahan kode aplikasi pada sesi UAT ini; hanya artefak QA di `.local/` (gitignored).
