# Lowongan internal

Menu Rekrutmen membuka `/vacancies`, dengan tab Lowongan dan Pipeline (pipeline masih contoh berlabel). Lowongan belum dipublikasikan ke internet; status Dibuka berarti siap digunakan HR untuk rekrutmen internal. Halaman karier publik dan pelamar berada di tahap berikutnya.

Implementasi: tabel vacancies sesuai ERD + version, enum status/jenis kerja, policy Admin/Staf HR dapat tambah/edit/status, Viewer membaca. Form memuat judul, departemen, jenis kerja, lokasi, deskripsi, persyaratan, tanggal buka/tutup dan status. Daftar dengan search/filter/pagination15; detail dan edit. Menutup lowongan menyimpan data; tidak menyediakan hapus permanen. Dashboard menghitung lowongan published dalam rentang tanggal WIB dari database. Rekrutmen kandidat tetap contoh, dengan navigasi yang membedakan lowongan aktual dan pipeline contoh.

Urutan: tes RED; backend dan migration additive; frontend konsisten shell HR; quality gate dan review; browser desktop/mobile. Tidak push/deploy, tidak reset database development. Tes menggunakan database terpisah.
