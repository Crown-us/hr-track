# Jadwal kerja massal

> Pelaksana superpowers:executing-plans di checkout ini; jangan commit/push/deploy. Pengguna menyetujui tahap jadwal kerja massal setelah kalender absensi.

**Goal:** Admin/Staf HR menjadwalkan beberapa pegawai dengan pratinjau yang dapat diperiksa dan tanpa menimpa catatan lama.

**Architecture:** Gunakan attendance_days/attendance_events yang ada, tanpa migration/dependency baru. AttendanceSchedules membentuk rencana preview dan commit. Mutasi dan preview konsisten: mutex akun→current employee locks terurut. Preview menghasilkan token terenkripsi terikat actor/auth_version, payload tervalidasi, fingerprint rencana dan expiry15menit. Commit mendekripsi, memvalidasi kembali payload, mengambil locks yang sama, menghitung ulang plan dan membandingkan fingerprint. Tidak mempercayai angka/rows buatan client. Semua insert+audit satu transaksi; kegagalan membatalkan seluruh batch. Replay setelah berhasil ditolak karena fingerprint berubah. batch_id UUID pada changes JSON mengelompokkan audit.

**Scope / pilihan implementasi:** HR saja, /attendance/schedules, cari/pilih pegawai aktif (maks50, pilihan tetap tersimpan lintas pencarian). Rentang mulai hari ini hingga90hari ke depan, maksimal31hari inklusif, hari ISO1–7, jam mulai/akhir WIB; opsi akhir hari berikutnya untuk malam, durasi>0≤24jam; toleransi0–120menit; alasan10–2000karakter. Maks1000pasangan pegawai/tanggal terpilih, tidak memotong diam-diam. Tidak pola shift permanen, penggantian jadwal massal, absen pegawai, payroll atau notifikasi.

Setiap pasangan terpilih ditampilkan sebagai dibuat/dilewati. Lewati semua attendance_day yang sudah ada termasuk voided (tetap koreksi lewat detail), libur kantor bila skip_holidays=true (default), tanggal work_dates approved/cancel_pending, sebelum join, jadwal mulai<=now, serta overlap jadwal aktif pada tanggal lain. Catatan pada hari cuti tidak ditulis. Jadwal malam bersinggungan dengan awal jadwal hari lain ditolak/dilewati; batas akhir=awal berikutnya boleh. Pegawai yang hilang/nonaktif/arsip ditolak, bukan dilewati diam-diam. Pratinjau seluruh rows≤1000 dengan pagination client25 agar tetap bisa ditinjau tanpa memotong. Summary actual kandidat/dibuat/dilewati, tanpa data kehadiran pribadi atau alasan cuti.

Pratinjau bukan persetujuan mutasi: tombol simpan baru aktif sesudah konfirmasi dan isian masih cocok preview. Edit input mengharuskan preview ulang. Preview lintas tab atau role/sesi berubah tidak dapat memindah actor. Expiry dan conflict error tampil di luar form snapshot; data tidak disimpan sebagian. Jadwal baru tidak berisi clock_in/out sehingga bukan klaim hadir. Seluruh pilihan/pola tercakup reason+batch_id audit. Tidak ada perubahan schema.

## Tasks
- [x] RED AttendanceScheduleTest: endpoint404, role/scope/input bounds, WIB/night, skip existing/holiday/leave/start/join/overlap, preview tamper/expiry/actor, stale/replay/audit atomicity.
- [x] AttendanceSchedules service, AttendanceScheduleRequest/Controller, routes before dynamic employee routes, GET current selections + preview props, encrypted tokens & transaction/audit. GREEN10tes124assertions.
- [x] attendance-schedules.tsx/CSS, HR Absensi entry CTA, employee search/retained chips, weekdays/times, preview summary+25row pagination and final confirmation. Existing styling/calendar preserved.
- [x] All project gates154tests2357assertionsPASS, Pint/lint/format/types/buildPASS; independent review dan browser desktop/mobile preview→save→calendar. QA cleanup, docs.

## Ledger
2026-11-03: semua perubahan cuti/kalender dan perubahan user pada seeders/dashboard/dokumen dipertahankan. Implementasi langsung tanpa worktree baru sesuai instruksi pengguna. Desain menggunakan shell putih/navy yang sama, tabel pratinjau dan angka aktual; tidak mengubah landing.

Review menemukan token alasan Unicode maksimal terlalu panjang dan false dirty ketika reason berakhiran spasi. Regression Unicode2000emoji RED lewat HTTP karena batas token, lalu JSON_UNESCAPED_UNICODE membuat GREEN; reviewer mengonfirmasi token worstcase teruji<30k tanpa DB. Browser mereproduksi preview valid namun confirmation disabled karena TrimStrings; signature UI kini trim reason. Browser susulan alasan trailing-space valid, confirmation enabled; perubahan jam18:00 menandai dirty dan disable, kembali17:00 cocok dengan pratinjau. Expiry UI memakai timer effect, bukan Date.now saat render.

Browser: login HR→Absensi→Jadwal massal, pilih Nadia lalu Raka melalui pencarian terpisah (pilihan bertahan),9–13Nov menghasilkan10baris,6baru/4skip (existing/cuti/libur). Setelah konfirmasi, benar-benar tersimpan6 dan detail kalender Raka12Nov menampilkan08–17WIB, jam hadir blank serta audit reason. Desktop1280, tablet768/doc753 dan phone390/doc375 diperiksa, consolewarn/error kosong. Pagination>25baris belum diuji lewat browser (source review; bounds/backend diuji). Source Taste lint2file0FAIL/0WARN, inspector otomatis tidak dijalankan. Bukan stress concurrent-worker/UAT. Artefak ignored schedules-preview/saved/mobile PNG; tidak commit/push/deploy.
