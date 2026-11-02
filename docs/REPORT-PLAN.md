# Laporan Excel dan PDF

Pengguna menyetujui SP dahulu lalu laporan. Empat rekap dari database aktual: lamaran (tanggal melamar), evaluasi pelatihan (tanggal mulai sesi WIB), kontrak (tanggal mulai kontrak), pelanggaran/SP/konseling (tanggal kejadian). Pemilih tanggal wajib dan maksimal366hari, status opsional per modul. Halaman menampilkan total hasil dan preview10baris; ekspor xlsx maksimal1000baris, PDF200baris. Lebih besar harus dipersempit, bukan dipotong diam-diam.

Admin/Staf HR saja untuk seluruh halaman/ekspor. Ekspor memuat kolom terpilih (nama, nomor, periode/status dan skor yang relevan), tidak mengirim telepon/email, uraian kasus, catatan konseling, path berkas atau audit JSON. PKWTT akhir kosong, skor belum terisi kosong, nilai0 tetap0, selisih hanya pasangan lengkap hadir. Kontrak/catatan dibatalkan hanya masuk jika statusnya dipilih atau semua. Arsip pegawai tetap tercatat untuk riwayat. Semua filter berlaku identik pada preview dan ekspor.

File xlsx asli OOXML satu sheet melalui XMLWriter/ZipArchive bawaan PHP (sudah tersedia), semua nilai sebagai inlineStr agar isi seperti =HYPERLINK tidak menjadi formula; tidak ada macro/external link. Tidak memasang tool/dependency global. PDF menggunakan Dompdf yang sudah terpasang, escaped Blade, remote/PHP/JS nonaktif, A4 landscape, header tabel berulang, nomor halaman. Kedua unduhan privat/no-store/nosniff. Temp xlsx hanya sementara, cleanup saat gagal dan deleteFileAfterSend. Tidak menyimpan laporan permanen atau mengirim email.

## Tugas
- [x] RED preview/filter/role, XLSX ZIP/XML/formula literal, PDF MIME/header dan limit yang tidak memotong data.
- [x] ReportRequest, HrReports query/projection service, TabularWorkbook export, ReportController, Blade pdf.hr-report, routes/private headers.
- [x] reports.tsx pemilih modul/status/tanggal, preview/total dan tombol ekspor nativeanchor; sidebar Laporan aktif untuk HR.
- [x] GREEN seluruh gate, render PDF visual, browser responsive, review dan dokumentasi. Sanitasi XML menghapus U+FFFE/U+FFFF selain kontrol C0. XLSX diverifikasi struktur/XML dan unduh browser; belum dibuka di aplikasi Excel desktop.
