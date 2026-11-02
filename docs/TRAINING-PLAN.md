# Manajemen pelatihan

Lingkup: jadwal judul/pemateri/lokasi/mulai-selesai WIB, materi PDF privat berversi, peserta dari pegawai aktif, kehadiran, nilai pre/post-test0–100, dan selisih poin. Menu Pelatihan aktif pada workspace. Tanpa sertifikat, email, impor massal atau ekspor laporan pada tahap ini.

Admin/Staf HR mengelola; Viewer melihat jadwal dan jumlah peserta, tanpa roster, nilai, catatan audit atau dokumen privat. Semua endpoint memakai session/policy. Timestamps UTC, input/tampilan WIB. Jadwal boleh lampau untuk pencatatan pelatihan yang telah berlangsung. Status scheduled/completed/cancelled; selesai hanya setelah waktu berakhir, batal memerlukan alasan. Tidak ada hard delete. Setelah selesai/batal metadata jadwal tidak diubah. Perubahan waktu setelah nilai dicatat ditolak agar evaluasi tidak berpindah sesi.

Peserta unik per pelatihan/pegawai, hanya pegawai tidak diarsip dan bukan Nonaktif dapat ditambahkan. Pegawai yang diarsip setelah pendaftaran tetap ada pada riwayat pelatihan. Kehadiran registered/attended/absent. Pre-test/post-test boleh kosong (bukan nol otomatis). Post-test hanya sesudah sesi berakhir, kehadiran hadir, dan pre-test sudah ada. Pencatatan nilai dapat dilanjutkan setelah status selesai; status batal menolak mutasi. Tidak mengambil keputusan pekerjaan berdasarkan nilai.

Ringkasan: jumlah peserta terdaftar, pasangan nilai lengkap, rata-rata pre/post dan peningkatan hanya dari pasangan lengkap. Selisih merupakan poin post−pre, bukan persentase efektivitas. Nol adalah nilai valid; skor hilang tidak masuk rata-rata. Materi PDF5MiB diperiksa MIME/ekstensi/header, UUID privat, upload baru menambah versi tanpa menghapus lama; rollback membersihkan file. Viewer/guest ditolak untuk unduh dan nilai.

Migration additive000009: trainings, training_participants, training_materials, training_events (audit append-only actor/UTC/JSON). FK RESTRICT, unique peserta, CHECK status/skor/waktu. Training.version untuk revisi/daftar peserta/status; participant.version untuk evaluasi. Semua mutasi lock training terlebih dulu lalu peserta. Audit dan perubahan satu transaksi. Form memakai versi snapshot dan remount saat versi berubah.

Tes: kebijakan role/guest, WIB→UTC, validasi jadwal/PDF/pegawai/skor, duplikasi/versi lama, perhitungan pasangan lengkap termasuk pre0, pembatalan/penyelesaian dan rollback. Seluruh gate AGENTS, review kode, cek browser desktop/mobile memakai test DB. Tidak reset/seed development, push/deploy.
