# Galeri screenshot — semua modul & form CRUD

Diambil otomatis dari instance staging `http://127.0.0.1:8002` pada 4 November 2026. **58 halaman**, resolusi 1440×900, semuanya status OK (tidak ada 404).

- Gambar: `.local/uat/gallery/*.png` (gitignored)
- Metadata: `.local/uat/gallery/index.json`
- Ringkasan kontak-sheet: `.local/uat/gallery/sheet-*.png`

Setiap modul difoto pada halaman **daftar (R)**, **form tambah (C)**, **halaman detail (R)**, dan **form ubah (U)** bila tersedia — termasuk **form arsipkan pegawai (D)**.

## Halaman publik (tanpa login)

| No | Halaman | URL | Isi |
|----|---------|-----|-----|
| 1 | Beranda publik | `/` | Hero, fitur, lowongan terbaru |
| 2 | Karier | `/karier` | Daftar lowongan + pencarian |
| 3 | Detail lowongan | `/karier/1` | Kualifikasi & alur seleksi |
| 4 | Form lamaran publik | `/karier/1/lamar` | Form CV + consent |
| 5 | Masuk | `/login` | Login akun |

## Admin / Staf HR — semua modul

| No | Modul | URL | CRUD |
|----|-------|-----|------|
| 6 | Ringkasan HR | `/hr` | Dashboard |
| 7 | Direktori pegawai | `/employees` | R (list + filter) |
| 8 | Form pegawai | `/employees/create` | C |
| 9 | Detail pegawai | `/employees/2` | R |
| 10 | Ubah pegawai | `/employees/2/edit` | U |
| 11 | Arsipkan pegawai | `/employees/2/archive` | D (alasan + PDF + konfirmasi) |
| 12 | Departemen | `/departments` | R |
| 13 | Form departemen | `/departments/create` | C |
| 14 | Ubah departemen | `/departments/1/edit` | U |
| 15 | Jabatan | `/positions` | R |
| 16 | Form jabatan | `/positions/create` | C |
| 17 | Ubah jabatan | `/positions/1/edit` | U |
| 18 | Profil pelamar | `/applicants` | R |
| 19 | Form profil pelamar | `/applicants/create` | C |
| 20 | Lowongan (HR) | `/vacancies` | R |
| 21 | Form lowongan | `/vacancies/create` | C |
| 22 | Detail lowongan | `/vacancies/1` | R |
| 23 | Ubah lowongan | `/vacancies/1/edit` | U |
| 24 | Pipeline lamaran | `/applications` | R |
| 25 | Catat lamaran | `/applications/create` | C |
| 26 | Detail lamaran | `/applications/1` | R + ubah tahap |
| 27 | Penawaran kerja | `/applications/1/offering` | C/U + respons kandidat |
| 28 | Agenda interview | `/interviews` | R |
| 29 | Jadwalkan interview | `/interviews/create` | C |
| 30 | Kontrak | `/contracts` | R |
| 31 | Form kontrak | `/contracts/create` | C |
| 32 | Detail kontrak | `/contracts/1` | R + unggah PDF |
| 33 | Koreksi kontrak | `/contracts/1/edit` | U |
| 34 | Pelatihan | `/trainings` | R |
| 35 | Form pelatihan | `/trainings/create` | C |
| 36 | Detail pelatihan | `/trainings/1` | R + peserta/materi/status |
| 37 | Disiplin | `/discipline` | R |
| 38 | Form disiplin | `/discipline/create` | C |
| 39 | Detail disiplin | `/discipline/1` | R + tindak lanjut |
| 40 | Cuti | `/leave` | R |
| 41 | Pengaturan cuti | `/leave/settings` | U (saldo, hari libur) |
| 42 | Absensi HR | `/attendance` | R |
| 43 | Kalender kehadiran | `/attendance/employees/2` | C/U catatan harian |
| 44 | Jadwal massal | `/attendance/schedules` | C (preview + commit) |
| 45 | Rekap absensi | `/attendance/recap` | R + ekspor |
| 46 | Koreksi absensi | `/attendance/corrections` | R |
| 47 | Kelola akun | `/accounts` | C/U undangan & akses |
| 48 | Atasan langsung | `/accounts/reporting` | U |
| 49 | Laporan HR | `/reports` | R + ekspor XLSX/PDF |

## Portal pegawai

| No | Halaman | URL | Isi |
|----|---------|-----|-----|
| 50 | Data saya | `/me` | Profil & penempatan |
| 51 | Absensi saya | `/attendance` | Kalender + catat masuk/pulang |
| 52 | Cuti saya | `/leave` | Daftar pengajuan |
| 53 | Ajukan cuti | `/leave/create` | C |
| 54 | Koreksi absensi saya | `/attendance/corrections` | R |
| 55 | Data pribadi | `/me` | Kontak & detail |

## Portal atasan

| No | Halaman | URL | Isi |
|----|---------|-----|-----|
| 56 | Tim saya | `/team` | Daftar anggota |
| 57 | Persetujuan cuti tim | `/leave?scope=team` | Approve/reject |
| 58 | Kehadiran anggota | `/attendance/employees/2` | Kalender tim |

## Catatan

- Halaman detail/edit memakai data seed staging (pegawai id 2 = Andi Pratama; lowongan/kontrak/pelatihan id 1).
- Semua request di sesi ini **GET** — tidak ada data yang diubah saat pengambilan screenshot.
- Alur CRUD tulis sudah diuji terpisah: `docs/UAT-CRUD.md` (29 langkah, 29/29 PASS).
- Modul yang belum punya halaman detail sendiri: profil pelamar (daftar + form saja), departemen/jabatan (daftar + form).
