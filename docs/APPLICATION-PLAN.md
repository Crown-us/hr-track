# Pelamar dan lamaran internal

Scope disetujui: input HR, pilih lowongan, CV PDF privat, Screening awal, pipeline database dan riwayat status. Hemat perubahan dengan pola form/shell yang sudah ada; tanpa dependency tambahan, karier publik, email, jadwal interview atau fitur hapus.

1. Tes RED untuk identitas terpisah/lamaran ganda, role, lowongan terbuka, transisi legal/terminal/versi, CV privat/validasi/rollback dan filter.
2. Migration additive applicants, applications+version, application_status_histories append-only, applicant_documents. Unique email dan pasangan pelamar/lowongan; FK RESTRICT. Service transaksional dengan row lock; berkas dibersihkan jika DB gagal.
3. `/applications` board/daftar dengan pencarian/filter/pagination15 dan jumlah tahap seluruh hasil; board menampilkan halaman aktif (bukan pagination terpisah per kolom). Tambah pelamar baru atau pilih profil terdaftar via pencarian20 hasil. CV opsional saat membuat lamaran dan bisa ditambah sebagai versi baru dari detail. Satu CV milik profil pelamar, dapat dipakai di semua lamarannya.
4. Admin/Staf HR input/status/CV; Viewer hanya membaca informasi dasar, tanpa catatan privat/CV/nomor kontak. Actor berasal dari session. Screening→Interview/Ditolak; Interview→Offering/Ditolak; Offering→Diterima/Ditolak; terminal tidak dibuka ulang. Perubahan manual bertombol, tanpa drag/drop atau seleksi otomatis.
5. Dashboard private mengambil jumlah/aktivitas aktual; pratinjau lokal tetap contoh. Quality gate, review terbatas dan smoke browser desktop/mobile.

Batas: belum edit profil/hapus/arsip pelamar, retensi dokumen, antivirus/sanitasi PDF, pengelolaan jadwal, PDF undangan atau portal publik. PDF unduhan attachment dan nosniff, disk serve=false. Tidak reset DB development atau push/deploy.
