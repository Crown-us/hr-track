# Cuti & izin — implementation plan

> Pelaksana: gunakan superpowers:executing-plans di checkout ini, tanpa commit/push atau dependency baru. Pengguna meminta langsung melanjutkan modul berikutnya.

**Goal:** Pegawai mengajukan cuti/izin, atasan meninjau tim langsung, HR mengambil keputusan final dan mengatur saldo serta hari libur.

**Architecture:** Laravel session + Inertia/React yang ada. LeaveRecords memusatkan transaksi, akses, kalender dan saldo. Lock account_administration_lock lalu pegawai/pengajuan menyerialisasi mutasi dengan perubahan akun/atasan. Saldo tersedia dihitung dari alokasi dikurangi pengajuan aktif; audit append-only dalam transaksi yang sama. Tidak ada saldo otomatis berdasarkan dugaan kebijakan perusahaan.

**Spec / keputusan implementasi:** Jenis awal tetap: cuti tahunan (memakai saldo), sakit dan izin (tanpa saldo tahunan). Hari penuh, kalender Senin–Jumat dikurangi hari libur yang dimasukkan HR. Pengajuan satu tahun, maksimal rentang60hari, tidak sebelum tanggal bergabung; tahunan mulai hari ini/masa depan, sakit/izin boleh7hari ke belakang. Tahun berjalan atau berikutnya. HR menetapkan alokasi0–366hari per pegawai/tahun, dengan versi dan alasan; tidak boleh di bawah jumlah tertahan+terpakai. Ini konfigurasi aplikasi, bukan penetapan hak menurut hukum/peraturan perusahaan.

Pengajuan menyimpan daftar tanggal kerja sebagai snapshot, tidak berubah saat HR memperbarui hari libur. Pending/approved/cancel_pending mereservasi tanggal dan saldo; tumpang tindih jenis apa pun ditolak. Menolak/membatalkan melepas reservasi; tidak hard delete. Bukti PDF privat≤5MiB opsional, hanya pemohon/HR dapat mengunduh; atasan melihat jenis/tanggal/alasan pengajuan tetapi tidak berkas. Riwayat alasan keputusan terlihat pada pemohon dan HR, atasan hanya pengajuan anggota langsung.

Ada atasan supervisor aktif: pending_supervisor → pending_hr → approved/rejected. Tanpa atasan yang memenuhi syarat: langsung pending_hr. Atasan menggunakan relasi saat keputusan (bukan akun lama); HR dapat menangani pending_supervisor hanya jika tidak ada atasan yang memenuhi syarat saat itu. Tidak ada persetujuan diri sendiri (termasuk akun HR tertaut pegawai). Pemohon dapat membatalkan pending; untuk approved sebelum tanggal mulai, minta pembatalan → cancel_pending → HR setujui cancelled atau tolak kembali approved. HR boleh membatalkan approved sebelum mulai dengan alasan. Setelah mulai, tidak ada pembatalan administratif di fase ini. Keputusan HR tetap dapat menyelesaikan pengajuan pegawai yang kemudian nonaktif/arsip; tindakan portal memerlukan pegawai aktif.

**Batas:** Belum shift, setengah hari, carry-over/accrual, kuota khusus sakit/izin, delegasi persetujuan, kalender tim publik, email, absensi atau payroll. Tidak menambah akun/data contoh development. Perubahan lain yang telah ada dipertahankan.

## Review focus
- Dua pengajuan memakai saldo/tanggal sama: serialisasi dan hitung ulang di transaksi.
- Role/atasan berubah, self approval, pegawai arsip: periksa ulang actor di transaksi; scope server untuk setiap baca/unduh/mutasi.
- Tahun baru/WIB/hari libur: tanggal snapshot konsisten, satu tahun dan maksimal60hari.
- Audit/file gagal: seluruh perubahan rollback dan file dibersihkan.
- Form lama: versi snapshot ditolak, tidak mengirim versi baru dengan input lama.

## Tasks
- [x] 1. RED tests/Feature/LeaveTest.php: role/scope, alokasi, hari kerja, pengajuan/status/saldo, konflik/stale, bukti privat, rollback. Run php artisan test --filter=LeaveTest; kegagalan awal endpoint404 (sesudah MySQL dan field fixture diperbaiki).
- [x] 2. Migration000013 leave_balances/leave_holidays/leave_requests/leave_events; Model LeaveRequest, LeaveRecords service dan LeaveController/LeaveFormRequest. Saldo memakai query builder (model LeaveBalance terpisah tidak diperlukan). Routes /leave dan /leave/settings hanya Admin/HR atau portal sesuai aksi. GREEN11tes156assertions.
- [x] 3. UI leaves.tsx + leave.css; shared portal-shell.tsx ekstraksi shell portal tanpa perubahan data profil. HR sidebar menu Cuti & izin; portal menu pengajuan dan persetujuan. Halaman daftar/detail/create/settings dengan forms snapshot, status, riwayat dan kosong/galat.
- [x] 4. Semua gate proyek, review independen, browser desktop/tablet/mobile, migration additive dev tanpa reset, ERD/PLAN/PROGRESS + batas aktual. Final137tests1974assertionsPASS, Pint/lint/format/types/buildPASS. Tidak commit/push.

## Ledger
2026-11-03: inspeksi awal menemukan perubahan user pada seeders, dashboard, routes, ERD/PROGRESS/README dan InterviewTest. Tidak direset atau ditimpa. Gunakan file baru dan sisipan terarah. Runtime/DB test tetap terpisah.

Ruling: supervisor yang masih menjadi atasan langsung boleh membaca riwayat keputusan pengajuan anggota timnya; tidak ada catatan HR tersembunyi dalam modul ini. Berkas tetap hanya pemohon/HR. Pegawai yang kemudian nonaktif/arsip ditangani HR, bukan supervisor.

Review independen menemukan pembacaan relasi pegawai lama sesudah current lock dan galat konflik yang hilang saat remount. Tes dua koneksi MySQL mereproduksi RED; setRelation pegawai terkunci + eligibility atasan current read memperbaiki GREEN. LeaveCurrentReadTest memiliki guard env/database/akun sebelum DatabaseMigrations; tidak menjalankan refresh development. Shared props errors.version tetap di luar form; browser dua tab membuktikan pesan konflik tampil dan keputusan lama tidak tersimpan. Hari/timestamp mengikuti WIB/UTC.

Style: daftar saldo menjawab “berapa hari tersisa dan apa status pengajuan”; pengaturan menjawab “alokasi siapa yang harus disesuaikan”. Flat ledger dipilih dari draw workspace dengan palette/type lama dipertahankan. Pengecualian brand: tombol navy, status pastel dan shell/topbar tetap mengikuti arahan pengguna. Angka aktual dari DB, tanpa chart/angka pertumbuhan buatan. Source lint3file0FAIL/0WARN; browser manual desktop1280/tablet768/phone390 menggantikan inspector otomatis yang tidak dijalankan. Tidak mengklaim seluruh house-style lulus.
