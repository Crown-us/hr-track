# Uji alur rekrutmen

Lingkup: lowongan HR, karier publik, lamaran tanpa akun, CV privat, screening, interview, offering dan keputusan akhir. Gunakan data fiktif; keputusan uji tidak menyatakan hasil seleksi orang sungguhan.

## Bukti otomatis

`tests/Feature/RecruitmentJourneyTest.php` menguji endpoint HTTP aplikasi secara berurutan menggunakan database test terpisah, session login/logout, penyimpanan CV fake dan waktu yang dikendalikan. Ini pengujian integrasi, bukan bukti UAT pengguna HR atau otomasi klik browser.

| Skenario | Hasil yang diperiksa |
| --- | --- |
| HR membuat draft lalu menerbitkan | Draft tidak dapat dibuka publik; setelah diterbitkan muncul di landing, pencarian dan detail karier |
| Pelamar mengirim tanpa akun | Form dapat dibuka, CV tersimpan privat dan terkait lamaran, status awal Screening |
| HR membuka lamaran | Sumber publik, penanda email belum terverifikasi, history actor publik dan CV tampil sesuai izin |
| Screening ke Interview | Riwayat mencatat HR; jadwal WIB disimpan UTC dan undangan PDF dapat diunduh |
| Lowongan ditutup | Karier menyembunyikan posisi; lamaran yang sudah ada tetap dapat diproses |
| Interview selesai ke Offering ke Diterima/Ditolak | Kedua cabang diuji; empat riwayat berurutan, actor benar, interview selesai tetap tersimpan dan dashboard sesuai |
| Tahap akhir dan Viewer | Tahap akhir tidak dapat dibuka kembali; Viewer tidak dapat mengubah tahap atau mengunduh CV |
| Tengah malam WIB | Posisi tersedia pada tanggal buka dan sampai akhir tanggal tutup; setelah 00.00 WIB detail dan pengiriman ditolak tanpa menyimpan file/lamaran |

Jalankan ulang: `php artisan test --filter=RecruitmentJourneyTest`. Seluruh tes: `php artisan test`. Jangan menggunakan `migrate:fresh` pada database development.

## Checklist uji pengguna HR

Jalankan pada lingkungan uji dengan akun HR khusus dan identitas fiktif. Jangan menguji keputusan akhir dengan kandidat nyata.

1. Masuk sebagai HR, buat draft lowongan bertanda UJI. Periksa tanggal, deskripsi dan persyaratan. Pastikan posisi belum tampil di karier publik.
2. Ubah menjadi Dibuka dengan rentang tanggal hari ini. Buka karier di jendela tanpa sesi HR; cari posisi dan periksa detailnya.
3. Isi form tanpa akun dengan email `@example.test`, CV PDF fiktif dan persetujuan. Pastikan halaman terima kasih tampil. Pada lingkungan lokal, jalankan worker dan ambil tautan verifikasi dari log privat (mailer log); alamat fiktif tidak menerima email nyata. GET tautan hanya membuka konfirmasi; tekan tombol verifikasi untuk menyelesaikannya. Pada lingkungan SMTP uji gunakan mailbox uji yang dikuasai tim.
4. Kembali ke HR. Cari pelamar, buka lamaran, periksa sumber publik dan penanda email terverifikasi. Sebelum konfirmasi email, perubahan tahap harus ditolak. Unduh CV dan pastikan sesuai kandidat uji.
5. Pindahkan ke Interview, buat jadwal WIB dan unduh PDF. Cocokkan posisi, kandidat, tanggal, jam serta lokasi. Catatan privat tidak boleh ada di PDF.
6. Tutup lowongan; pastikan hilang dari karier. Pastikan lamaran lama tetap dapat dibaca dan diproses.
7. Setelah interview selesai, lanjutkan ke Offering. Buka Penawaran, respons & data pegawai; pilih departemen/jabatan/status, tanggal mulai dan batas respons. Simpan dan periksa ringkasan PDF internal. Catat penerimaan dengan tanggal, catatan dan bukti PDF fiktif; tahap menjadi Diterima. Gunakan lamaran fiktif lain untuk Ditolak kandidat/Ditarik HR. Periksa timeline dan dashboard setelah setiap langkah.
8. Pada penawaran diterima, periksa nama/telepon hasil prefill, isi nomor pegawai/email kerja, konfirmasikan identitas dan buat pegawai. Pastikan penempatan/status/tanggal mengikuti penawaran. Klik ulang atau lamaran lain dari profil yang sama tidak boleh membuat pegawai kedua, termasuk jika pegawai diarsipkan. Coba dua tab: setelah tab B merevisi, submit tab A harus ditolak dan isian lama dimuat ulang, termasuk catatan/bukti respons.
9. Masuk sebagai Viewer: data baca yang diizinkan tersedia, tombol perubahan, penawaran, bukti dan CV privat tidak tersedia. Keluar akun: halaman HR harus meminta login.
10. Catat hasil, perangkat, waktu WIB, langkah reproduksi, hasil aktual dan yang diharapkan. UAT HR belum dianggap selesai sampai pengguna menjalankan serta menilai checklist ini.

## Batas yang masih berlaku

Akses mailbox pelamar diverifikasi per lamaran, tetapi HR tetap perlu memeriksa identitas/CV. Notifikasi verifikasi dan bukti penerimaan otomatis membutuhkan worker dan mailer; lokal memakai log. Undangan dan ringkasan penawaran PDF diunduh manual, tidak dikirim email. Ringkasan penawaran internal belum menggantikan surat resmi/kontrak perusahaan. Konversi pegawai dipicu HR setelah bukti diperiksa, tidak terjadi otomatis saat status berubah. Belum tanda tangan, pemindaian antivirus CV, payroll atau notifikasi hasil seleksi kepada pelamar. Kandidat diterima lama tanpa penawaran memerlukan peninjauan/manual lewat direktori pegawai, bukan penawaran fiktif bertanggal mundur.
