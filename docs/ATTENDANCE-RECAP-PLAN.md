# Rekap absensi bulanan

Goal: Admin/Staf HR melihat rekap bulanan dengan filter departemen/nama/nomor pegawai dan mengekspor XLSX/PDF. Langsung implementasi di checkout, tanpa commit/push/deploy dan tanpa dependency baru.

Satu aturan status bersama kalender dan rekap; ekstrak AttendanceStatus dari aturan kalender existing, tanpa mengubah semantik. Hari kerja mengikuti tanggal awal jadwal WIB, punch UTC, shift malam dihitung sekali. Hadir hanya pasangan lengkap, terlambat mengikuti toleransi, tanpa keterangan hanya jadwal selesai. Approved/cancel_pending mengikuti snapshot work_dates; konflik cuti/punch perlu diperiksa. Usulan koreksi pending tidak memengaruhi hasil. Tanggal kosong bukan absen; libur/mendatang/berjalan dipisahkan, sebelum bergabung tidak dihitung. Bulan2000-01 sampai bulan berjalan; data dibaca dalam satu transaksi konsisten dan satu waktu server.

Populasi: semua pegawai yang bergabung sebelum akhir bulan, termasuk nonaktif/arsip (status saat ini diberi label). Sistem belum memiliki tanggal akhir kerja historis lengkap, sehingga tidak mengasumsikan kewajiban hadir dari status pegawai. Belum data tetap netral, bukan mangkir. Filter pegawai/department digunakan sama untuk layar dan ekspor. Tidak mengungkap kontak, alasan cuti/kasus/koreksi, berkas atau audit privat.

Query batch pegawai+department, attendance_days hanya bulan terpilih, approved leave tahun terkait dan hari libur; tidak memanggil kalender tahunan perpegawai. Batas1000pegawai untuk perhitungan/Excel,200PDF; lebih banyak menolak dengan pesan filter dipersempit, tidak memotong diam-diam. Ringkasan seluruh hasil filter, tabel pagination15. Laporan hidup saat dibuat, bukan snapshot arsip; waktu dibuat/cutoff WIB dan filter tercetak. XLSX memakai TabularWorkbook existing inlineStr, PDF Blade escaped/Dompdf A4landscape remote/PHP/JSoff.

- [x] RED tests akses/validasi/filter, konsistensi kalender/batas bulan/shift, cuti/pending, data kosong, nilai0, privasi, XLSX/PDF dan batas.
- [x] GREEN AttendanceStatus bersama, AttendanceRecap batch, controller/request/routes dan ekspor existing.
- [x] UI rekap HR + link dari Absensi/Laporan, semantik jumlah hari-pegawai dan semua status jelas.
- [x] Review independen, gates lengkap, browser testDB desktop/mobile+download, PDF render visual, cleanup dan dokumentasi.

Review focus: current month cutoff WIB, konflik cuti, snapshot approved correction vs pending, query N+1/memory limit, export filter konsisten dan data privat tidak bocor. Tidak payroll/perhitungan gaji, jam lembur, persentase disiplin atau otomatis SP.
