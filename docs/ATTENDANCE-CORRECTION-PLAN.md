# Pengajuan koreksi absensi

Goal: Pegawai/Atasan mengajukan koreksi masuk/pulang sendiri; atasan langsung meninjau lalu HR memutuskan akhir. Implementasi langsung di checkout sesuai instruksi pengguna, TDD dan review independen, tanpa push/deploy.

Keputusan: hanya row jadwal aktif yang sudah selesai, tanggal kerja dalam31hari kalender terakhir (hari ini sampai30hari sebelumnya). Usulan wajib pasangan masuk/pulang dalam WIB, bukan masa depan, masuk dalam24jam dari awal, durasi>0≤24jam, tidak overlap aktual/row lain terbuka atau cuti approved/cancel_pending. Jadwal tidak dapat diubah dari pengajuan. Alasan10–2000karakter. Perubahan yang identik ditolak. Tidak upload bukti pada tahap ini.

Pending satu per hari. Tanpa atasan aktif langsung HR; jika atasan hilang HR dapat menangani pengajuan yatim. Atasan yang saat ini eligible dapat meneruskan/menolak tim langsung; HR keputusan akhir, tidak ada persetujuan diri sendiri. Pemohon dapat menarik pending. Tidak edit/hapus pengajuan; jika salah, tarik dan buat baru. Versi pengajuan melindungi tab lama; versi catatan absensi saat pengajuan wajib tetap sama saat persetujuan akhir. Jika catatan berubah, HR menolak dengan alasan dan pegawai mengajukan ulang. Penolakan/penarikan tidak mengubah absensi.

Arsitektur: migration000016 attendance_corrections + correction_events (FKRESTRICT, snapshot sebelum/proposal, versi). Service AttendanceCorrections menyerialisasi mutex akun→pegawai→attendance_day→pengajuan; gunakan aturan waktu AttendanceRecords untuk final write dalam transaksi yang sama, audit kedua modul atomik. Sumber waktu berubah menjadi HR; detik/sumber yang tidak berubah dipertahankan. Halaman daftar, form, detail/riwayat di /attendance/corrections; link dari kalender. UI putih/navy/typography existing dipertahankan.

- [x] RED HTTP tes workflow, scope/IDOR/self, duplicate/stale day/request, validasi waktu/cuti/overlap, withdraw/reject/orphan serta rollback.
- [x] GREEN schema/model/service/controller/routes; tidak reset/seeding development.
- [x] React form perbedaan lama/usulan + daftar/keputusan/riwayat, pagination dan error versi terlihat.
- [x] Review, gates wajib, QA browser pada testDB; migration additive development, dokumentasi hasil dan batas.

Review focus: current atasan/pegawai vs snapshot lama, keputusan sendiri, perubahan punch setelah pengajuan, shift malam dan detik pada waktu asli, kegagalan audit akhir membatalkan perubahan absensi.
