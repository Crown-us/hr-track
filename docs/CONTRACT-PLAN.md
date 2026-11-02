# Kontrak kerja dan pengingat

Tujuan: HR mencatat kontrak pegawai dan melihat kontrak PKWT yang berakhir dalam 30 hari. Melanjutkan roadmap setelah Pelatihan, memakai Laravel/Inertia/React dan desain HR yang ada, tanpa dependency baru.

Keputusan implementasi: PKWT wajib tanggal akhir >= mulai; PKWTT tanpa tanggal akhir. Tanggal adalah kalender WIB, timestamp audit UTC. Ini aturan input aplikasi, bukan pemeriksaan kepatuhan hukum. Nomor kontrak unik, pegawai tidak dapat diganti setelah pembuatan. Kontrak baru hanya untuk pegawai aktif dan tidak diarsipkan. Periode kontrak yang tidak dibatalkan tidak boleh bertumpuk per pegawai (batas inklusif); perpanjangan dibuat sebagai catatan baru setelah periode sebelumnya. Tidak mengubah status pegawai otomatis.

Admin/Staf HR membuat, mengoreksi dengan alasan, membatalkan dengan alasan, dan mengunggah PDF privat berversi maksimal 5 MiB. Pembatalan administratif menyimpan riwayat dan bukan pencatatan PHK. Tidak ada hapus permanen. Viewer membaca data dasar/tanggal saja, tanpa catatan, berkas atau riwayat privat. Koreksi dan pembatalan memakai versi snapshot, row lock pegawai lalu kontrak, dan audit satu transaksi. Berkas baru tidak menggantikan versi sebelumnya dan dibersihkan jika transaksi gagal.

Status tampilan dihitung: dijadwalkan/berjalan/berakhir/dibatalkan. Pengingat hanya PKWT tidak dibatalkan, pegawai aktif/tidak diarsipkan, mulai <= hari ini, akhir hari ini sampai +30 hari inklusif. Kontrak lewat tanggal dipisahkan. Jika sudah ada kontrak lanjutan tidak dibatalkan yang mulai tepat sehari setelah tanggal akhir, pengingat kontrak sebelumnya disembunyikan (riwayat tetap ada). Jeda antarperiode tetap perlu ditinjau. Pengingat dashboard dari query aktual, tidak membutuhkan worker/email.

UI: sidebar Hubungan kerja -> Kontrak; daftar dengan pencarian nomor/nama, filter perhatian, pagination15; form, detail tanggal/pegawai, berkas, audit, koreksi dan pembatalan. Dashboard menampilkan jumlah dan lima kontrak terdekat. Flat white/navy, tabel dan badge pastel mengikuti shell yang sudah disetujui. Pertanyaan utama layar: kontrak mana perlu ditinjau? Warna urgensi hanya pada tanggal/status, tidak dekorasi.

## Pelaksanaan

- [x] Tes HTTP RED: role, CRU/alasan/versi, tanggal/jenis, overlap dan arsip, PDF privat/rollback, batas pengingat WIB.
- [x] Migration additive000010, model EmployeeContract/ContractDocument, policy, ContractRequest/ContractActionRequest, ContractRecords, ContractController, route dan private headers. Redirect validasi/transaksi eksplisit ke create/edit/show tanpa bergantung Referer yang sengaja dibatasi.
- [x] UI contracts.tsx, sidebar, pengingat dashboard; form snapshot versi, unggah dan navigasi responsif.
- [x] GREEN seluruh suite97tes/1416assertions dan Pint/lint/format/types/build. Review independen untuk race, privasi dan batas kalender. Browser desktop/tablet/mobile, bukti dan batas hasil di PROGRESS.

Belum termasuk template kontrak hukum, tanda tangan elektronik, pengingat email, perhitungan kompensasi, pemutusan hubungan kerja atau pelanggaran/SP. Tidak deploy/push pada tahap ini.
