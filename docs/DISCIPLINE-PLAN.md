# Pelanggaran, SP dan konseling

Melanjutkan permintaan pengguna: SP dahulu lalu laporan. Laravel/Inertia/React, PHP>=8.3.36, MySQL development/test terpisah. Checkout saat ini, perubahan kontrak yang belum di-commit dipertahankan. Tidak deploy/push atau memasang dependency baru.

Keputusan implementasi: Admin/Staf HR mengelola catatan bernomor unik per pegawai, jenis pelanggaran/SP1/SP2/SP3/konseling, tanggal kejadian WIB tidak di masa depan, judul, uraian privat, dan tanggal tindak lanjut opsional >=kejadian. Pegawai baru harus aktif/tidak diarsipkan; riwayat pegawai arsip tetap tersedia. SP wajib PDF saat dibuat; berkas lain opsional, maksimal5MiB PDF privat berversi. Tingkat SP dipilih HR, tidak diputuskan atau dinaikkan otomatis oleh aplikasi. Tidak menerbitkan surat hukum/PHK atau menghitung masa berlaku SP.

Nomor, tanggal, judul, uraian dan tindak lanjut dapat dikoreksi saat terbuka dengan alasan dan snapshot versi. Pegawai/jenis immutable. Selesaikan dengan catatan hasil; pembatalan administratif dengan alasan mempertahankan seluruh data/berkas. Tidak hard delete/reopen. Setelah selesai tidak bisa edit/upload; pembatalan catatan salah tetap tersedia. Lock pegawai lalu catatan dan audit satu transaksi. Upload gagal audit dibersihkan. Viewer tidak dapat mengakses keseluruhan modul ini, termasuk detail/berkas/props.

UI: Hubungan kerja memiliki tab Kontrak / Pelanggaran & SP (HR saja), daftar pencarian/filter/pagination15, tambah/edit/detail, PDF berversi dan timeline audit, status pastel sesuai shell yang ada. Pertanyaan utama: catatan mana masih perlu ditindaklanjuti? Tidak ada KPI rekaan. Timestamp audit UTC, tampilan WIB, bahasa Indonesia biasa.

## Tugas
- [x] RED tes HTTP role, tanggal, wajib PDF SP, immutable/stale/alasan, selesai/batal, berkas privat/lintas ID dan rollback.
- [x] Migration000011, DisciplinaryCase/DisciplineDocument/policy, DisciplineRequest dan action request, DisciplineRecords, DisciplineController, routes/private headers.
- [x] UI discipline.tsx, navigation/tabs; capture version form, error/processing dan mobile.
- [x] GREEN suite dan pemeriksaan, review independen, browser, dokumentasi/ERD. Bukti di PROGRESS.md; UAT pengguna HR tetap diperlukan.

Fase selanjutnya laporan terpisah di REPORT-PLAN.md; absensi/cuti tetap belum dimulai sesuai pilihan pengguna.
