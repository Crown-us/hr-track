# Login dan aktivasi akun — 2 November 2026

Scope disetujui: login dan signup terlebih dahulu, Laravel Fortify + MySQL, desain mengikuti ruang HR yang sudah ada.

1. Tulis tes login/logout, akses guest, pembatasan login, undangan kedaluwarsa/sekali pakai dan penolakan role dari payload.
2. Pasang Fortify tanpa registrasi publik; session web, validasi Indonesia, rate limit. Tambahkan role dan tabel undangan dengan token hash, expiry dan waktu pemakaian. Signup hanya dari undangan.
3. Form React login/aktivasi akun, navigasi landing ke login, dashboard HR terautentikasi dengan identitas asli tetapi data bisnis tetap contoh.
4. Command lokal/operasional untuk membuat admin pertama (password tersembunyi) dan menerbitkan undangan. Tidak kirim email otomatis.
5. Jalankan seluruh quality gate dan cek desktop/mobile serta alur form di browser.

Ruling: signup berarti aktivasi akun melalui undangan, sesuai keputusan sebelumnya tentang akses internal; formulir publik tanpa token hanya menjelaskan cara meminta akses. Reset password/2FA, UI pengelolaan akun dan CRUD HR di luar perubahan ini. Tautan undangan disalurkan manual oleh operator; tidak ditulis ke source/log. Preview lokal tetap tersedia dan diberi label data contoh.
