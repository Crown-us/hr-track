# Pegawai tersimpan dan pengarsipan terkendali

Permintaan: hapus ikon «/breadcrumb Ruang kerja; tambah/baca/edit pegawai; penghapusan harus disertai berkas dan tidak mudah dilakukan tanpa alasan.

Keputusan implementasi: penghapusan dari direktori aktif adalah soft delete/arsip. Admin harus mengisi alasan minimal20 karakter, unggah PDF pendukung maksimal5MiB, ketik nomor pegawai dan konfirmasi. Staf HR dapat tambah/edit; viewer hanya membaca. Admin dapat memulihkan dengan alasan dan konfirmasi nomor. Tidak menyediakan endpoint hapus permanen; pemusnahan data harus mengikuti kebijakan retensi perusahaan yang ditentukan terpisah. Ini keputusan produk, bukan klaim kewajiban hukum universal.

1. Tes lebih dahulu: CRU, validasi/unique termasuk arsip, mismatch departemen/jabatan, role, arsip+file privat+audit+restore, rollback file jika transaksi gagal, pencarian/pagination dan konflik versi.
2. Migration additive departments/positions/employees/employee_events, model/enum/policy/requests, controller dan service transaction. Lock row + versi untuk menolak form lama. FK komposit menjaga integritas jabatan.
3. Shell bersama; daftar pegawai dari server dengan pencarian/filter/sort/pagination; form tambah/edit; detail dan riwayat admin; halaman arsip dengan konfirmasi eksplisit; pemulihan. Dashboard memakai jumlah pegawai aktual, rekrutmen tetap contoh berlabel.
4. Master departemen/jabatan contoh diisi idempoten, tanpa membuat pegawai fiktif di database pengguna. Tidak menghapus data yang ada.
5. Seluruh quality gate, review dan browser desktop/mobile. Tidak push/deploy.

Batas: belum ada workflow persetujuan dua orang, manajemen master via UI, antivirus/sanitasi PDF, dokumen personal pegawai atau retensi/pemusnahan terjadwal. Berkas arsip bersifat privat dan unduh attachment khusus Admin; tidak melakukan pengiriman dokumen.
