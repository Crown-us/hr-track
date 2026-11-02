# Akun, pegawai dan atasan — tahap 1–2

Pengguna menyetujui tahapan tabel role. Tahap ini menyelesaikan fondasi akun/otorisasi sebelum cuti dan absensi. Laravel/Fortify/session/Inertia/React yang ada dipertahankan, tanpa dependency baru, tanpa push/deploy. Perubahan kontrak/SP/laporan sebelumnya tidak dihapus.

Admin saja: daftar/cari akun dan undangan; undang akun dengan role admin/hr_staff/viewer/employee/supervisor, tautan48jam sekali pakai, dapat dicabut/diterbitkan ulang; perbarui role/status/link pegawai; buat tautan reset60menit sekali pakai; tetapkan atasan langsung pegawai. Mutasi wajib konfirmasi password Admin dan alasan10karakter untuk perubahan/reset/pencabutan/atasan. Tautan hanya tampil pada respons Admin setelah pembuatan, tidak dikirim email otomatis atau disimpan mentah di DB/audit. Reset memakai tabel password_reset_tokens bawaan dan aturan password yang sama; tidak ada password default.

users menambah is_active, employee_id nullable unique FK RESTRICT, version dan auth_version. Role employee/supervisor wajib pegawai aktif/tidak diarsipkan; HR/Admin/Viewer boleh tidak terhubung. Satu pegawai satu akun, termasuk akun nonaktif. Undangan aktif mereservasi pegawai; pemeriksaan ulang dilakukan saat aktivasi. Pegawai punya manager_employee_id nullable FK diri sendiri, tidak boleh self/cycle. Manager baru wajib pegawai aktif dengan akun supervisor aktif. Hanya tim langsung yang terlihat, bukan seluruh hierarki.

Tabel account_administration_lock singleton menyerialisasi mutasi akun, reservasi undangan, aktivasi, reset dan perubahan struktur atasan sehingga validasi unik/cycle tidak memakai snapshot lama. account_events append-only menyimpan actor/target/action/perubahan tanpa password/token. Pembaruan memakai versi, alasan, lock dan transaksi audit. Tidak boleh menonaktifkan/menurunkan role diri sendiri atau Admin aktif terakhir. Perubahan akses/reset merotasi remember_token dan auth_version; middleware memeriksa tiap request, termasuk session file. Login baru mengikat versi; sesi lama tanpa marker hanya diterima pada versi awal1, sehingga reset sebelum sesi pertama setelah migration tetap mencabut akses. Akun portal dengan pegawai arsip/nonaktif ditolak.

Login/aktivasi role lama tetap /hr; employee/supervisor /me. /me hanya profil pegawai sendiri dan atasan dasar. /team dan detail hanya supervisor aktif untuk anggota langsung, tanpa kontak, dokumen, kasus SP atau catatan HR. Semua endpoint HR lama tetap menolak dua role baru. Tidak menampilkan cuti/absensi palsu atau tombol belum bekerja. Tahap pengajuan/persetujuan cuti dan sumber absensi dikerjakan setelah fondasi ini.

## Tugas
- [x] RED keamanan Admin, reauth/self/versi, reservasi/aktivasi, reset/revokasi session, scope portal, manager cycle dan audit rollback. Admin aktif terakhir terlindungi melalui larangan self dan hitung Admin aktif di transaksi.
- [x] Migration000012, service AccountAdministration, request/controller/gates, middleware auth_version, login response dan aktivasi/CLI terhubung.
- [x] Halaman akun Admin, pemetaan atasan, portal data sendiri/tim dan reset auth mengikuti shell/font/palet yang ada.
- [x] Gate proyek lengkap, review independen dan browser desktop/mobile; update ERD/PROGRESS, tanpa reset development. Bukti125tes/1799assertions di PROGRESS.

Keputusan final: UI tidak menimpa undangan yang masih aktif; cabut dengan versi terlebih dahulu, lalu terbitkan pengganti. CLI legacy eksplisit tetap mendukung reissue. Penerbitan reset menaikkan users.version tanpa mencabut login saat itu; penggunaan reset atau perubahan akses menaikkan auth_version dan merotasi remember token. Perubahan akses juga menghapus tautan reset yang belum digunakan.
