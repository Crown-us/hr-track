# Script voiceover — UAT CRUD HRIS Lite

Untuk rekaman `.local/presentation/hris-lite-edit-os.mp4` (1920×1080, 30 fps, durasi **5:54**).

- Gaya: tenang, percaya diri, seperti menjelaskan ke calon pengguna (bukan iklan hard-sell).
- Tempo: ~140–150 kata/menit. Total naskah ± **690 kata** ≈ 5 menit bicara, sisa 50 detik untuk jeda.
- Sapaan "Anda", istilah teknis dibiarkan (Inertia, XLSX) tapi tidak dieja.
- Angka tanggal/timestamps tidak dibacakan; cukup alur.

## Naskah per segmen

| # | Timecode | Yang tampil di layar | Voiceover |
|---|----------|----------------------|-----------|
| 1 | 00:00–00:12 | Landing HRIS Lite, mockup laptop | "Ini HRIS Lite — ruang kerja HR dalam satu aplikasi. Rekrutmen, data pegawai, kehadiran, cuti, sampai laporan, semuanya di satu tempat." |
| 2 | 00:12–00:30 | Detail lowongan publik "Operations Staff" | "Mulai dari halaman publik. Pengunjung bisa melihat lowongan yang dibuka, membaca kualifikasi dan alur seleksinya, lalu melamar tanpa perlu membuat akun." |
| 3 | 00:30–00:45 | Section fitur "Ikuti setiap kandidat" | "Setiap modul saling terhubung. Berkas lamaran, wawancara, dan penawaran berada dalam satu perjalanan kandidat, bukan di spreadsheet terpisah." |
| 4 | 00:45–01:00 | Section "Kenali ruang kerjanya" (tabel & timeline) | "Tampilannya dibuat untuk dipindai cepat: tabel yang rapi, status yang jelas, dan riwayat yang bisa diikuti dari atas ke bawah." |
| 5 | 01:00–01:12 | Halaman karier (daftar lowongan) | "Halaman karier menampilkan semua posisi yang sedang dibuka, lengkap dengan departemen, jenis kerja, dan tanggal penutupan." |
| 6 | 01:12–01:40 | Halaman masuk, akun HR terisi, klik Masuk | "Masuk sebagai HR. Peran akun menentukan menu dan data yang bisa diakses — jadi admin, staf HR, atasan, dan pegawai melihat ruang kerja yang berbeda." |
| 7 | 01:40–02:05 | Daftar lowongan lalu form Tambah lowongan | "Membuat lowongan baru cukup satu form: judul posisi, departemen, jenis kerja, lokasi, deskripsi, syarat, dan periode penerimaan. Setelah dipublikasikan, lowongan langsung tampil di halaman karier." |
| 8 | 02:05–02:45 | Direktori pegawai lalu form Tambah pegawai (terisi "Michael") | "Di direktori pegawai, data bisa dicari berdasarkan nama, nomor, atau email. Menambah pegawai berarti mengisi identitas, penempatan, dan status kerja — departemen dan jabatan saling terhubung." |
| 9 | 02:45–03:05 | Detail pegawai (identitas, kontak, status) | "Halaman detail merangkum identitas, kontak, penempatan, dan riwayat pencatatan. Semua perubahan tercatat, lengkap dengan pelaku dan waktunya." |
| 10 | 03:05–03:35 | Pipeline lamaran (Screening, Interview, Offering) dan detail lamaran | "Lamaran masuk ke pipeline. HR memindahkan kandidat antar tahap — screening, interview, offering — dan setiap perpindahan menyimpan catatannya sendiri." |
| 11 | 03:35–03:50 | Form jadwalkan interview | "Wawancara dijadwalkan dari aplikasi: pilih pelamar, pewawancara, waktu, durasi, dan lokasi. Sistem menolak jadwal yang bentrok." |
| 12 | 03:50–04:05 | Halaman profil pelamar | "Profil pelamar menyimpan data kandidat lintas lowongan, jadi satu orang tidak perlu diinput berulang kali." |
| 13 | 04:05–04:30 | Form Tambah kontrak | "Kontrak kerja dibuat dengan nomor, jenis PKWT atau PKWTT, tanggal mulai dan berakhir. Periode yang tumpang tindih ditolak, dan berkas PDF bisa dilampirkan sebagai versi." |
| 14 | 04:30–04:50 | Daftar departemen, lalu form Tambah jabatan | "Data pendukung ikut dijaga: departemen dan jabatan. Mengubah nama aman, tapi memindahkan jabatan yang sudah dipakai pegawai akan ditolak sistem." |
| 15 | 04:50–05:15 | Ringkasan kehadiran dan rekap absensi | "Di absensi, HR melihat ringkasan kehadiran satu per satu pegawai, lalu membuka rekap bulanan: hadir, terlambat, cuti, sakit, dan tanpa keterangan — dengan filter departemen dan nama." |
| 16 | 05:15–05:35 | Halaman Kelola akun (daftar akun & undangan) | "Akses dikelola dari satu layar. Admin mengundang akun, mengatur peran, menghubungkan ke pegawai, dan bisa mencabut undangan yang belum dipakai. Setiap tindakan wajib disertai alasan dan kata sandi admin." |
| 17 | 05:35–05:54 | Halaman Laporan HR + unduhan; penutup | "Terakhir, laporan. Pilih modul dan periode, lalu unduh dalam Excel atau PDF. Satu sistem, satu sumber data — dan keputusan HR jadi lebih cepat." |

## Versi pendek (30 detik, untuk reels/shorts)

> "HRIS Lite: satu ruang kerja untuk rekrutmen, data pegawai, kehadiran, cuti, dan laporan. Lamaran masuk pipeline, wawancara terjadwal otomatis, absensi terekap per bulan, dan laporan siap diunduh. Data rapi, keputusan lebih cepat."

## Catatan produksi

- Jeda 0,5–1 detik di antara segmen; kalau ada aksi klik penting, biarkan narasi **berhenti** sesaat supaya kliknya terdengar "bersih" secara visual.
- Segmen 6, 8, dan 15 paling panjang — kalau tempo terasa cepat, potong satu kalimat di masing-masing.
- Nada akhir kalimat dibuat turun (bukan tanya), kecuali segmen 17 yang menutup.
- Kalau nanti dipasang ke Openscreen: `export --audio narasi.wav --audio-mode replace --auto-zoom`, dan atur `--audio-offset` kalau narasi mulai setelah intro.
