# Check-in / check-out pegawai

**Goal:** Pegawai dan Atasan mencatat kehadiran sendiri melalui web, mengikuti jadwal HR dan kalender yang sudah tersedia.
**Architecture:** POST terautentikasi dengan ID catatan, versi dan konfirmasi; server menentukan identitas serta waktu UTC. AttendancePunches memakai mutex akun, current read pegawai dan row lock yang sama dengan mutasi HR. React menampilkan panel aktivitas sebelum kalender, tetap putih/navy/font pilihan pengguna.
**Constraints:** PHP 8.3.36, tanpa dependency baru, database test terpisah; tidak reset development, tidak push/deploy. Perubahan OpenCode yang sudah ada dipertahankan.

Keputusan implementasi: masuk dibuka 60 menit sebelum awal sampai sebelum akhir jadwal. Pulang sesudah masuk sampai batas lebih awal antara 24 jam dari masuk dan 4 jam setelah akhir jadwal. Shift malam tetap melekat pada tanggal awal jadwal. Kelalaian melewati batas ditangani koreksi HR. Web tidak memverifikasi lokasi. Tidak ada punch tanpa jadwal, input jam sendiri, penggantian punch, atau punch atas nama tim. Cuti approved/cancel_pending memblokir masuk; pulang tetap boleh menutup catatan yang sudah masuk. Hari libur dengan jadwal eksplisit mengikuti jadwal HR.

Sumber disimpan per punch (web/hr); metadata lama dibaca sebagai HR. Koreksi HR hanya mengubah sumber waktu yang benar-benar berubah. Snapshot sebelum/sesudah dan waktu UTC tersimpan dalam audit. Versi lama/replay, akun berubah/nonaktif, pegawai diarsipkan, jadwal dibatalkan, punch bertumpuk, atau masuk lain yang belum ditutup ditolak.

## Pelaksanaan langsung (TDD)

- [x] Test HTTP sumber waktu server, IDOR/role/link/status, versi/replay, batas waktu, shift malam, cuti, tab basi, overlap, dan rollback audit; jalankan RED.
- [x] Tambah migration additive sumber masuk/pulang, service AttendancePunches, controller dan route; jalankan GREEN. Props hanya untuk pemilik, bukan supervisor yang melihat tim.
- [x] Panel Catat kehadiran: jadwal/waktu masuk-pulang, keterangan batas, konfirmasi dan aksi; refresh status sebelum aksi, backend tetap otoritas waktu. Kalender/riwayat menunjukkan sumber sebenarnya.
- [x] Review independen, gates lengkap, QA browser desktop/mobile database terpisah, dokumentasikan hasil/batas; tidak commit/push tanpa permintaan baru.

## Review focus

Identitas dari akun terkini, bukan request; shift lintas tengah malam; dua tab submit bersamaan; sumber sesudah koreksi campuran; waktu batas dan cuti berubah sesudah halaman dibuka. Semua mutasi menggunakan lock bersama modul terkait; uji HTTP tidak menggantikan stress lintas worker atau UAT HR.
