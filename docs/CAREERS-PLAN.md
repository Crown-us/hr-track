# Karier publik dan integrasi landing

Permintaan: nav Lowongan, hero Lihat lowongan, Masuk HR, tiga lowongan aktif terbaru, /karier daftar/detail, sekalian form publik tanpa akun (jawaban pengguna). Pertahankan bento/timeline/badge dan visual existing.

Publik hanya menerima DTO whitelist lowongan published dalam periode WIB, bukan model internal. Draft/ditutup/terjadwal/berakhir/arsip404. Search/filter/pagination12; landing maksimum3. Form CV PDF<=5MiB, consent, CSRF, rate limit3/jam/IP dan honeypot. Tidak mengirim email atau membuat akun. Balasan sukses sama untuk lamaran baru/duplikat; tidak mengungkap apakah email terdaftar.

Transaksi mengunci lowongan dan profil; profil existing tidak ditimpa. Simpan nama/telepon yang diajukan terpisah, source=public/email belum diverifikasi untuk pemeriksaan HR. CV publik terkait lamaran spesifik, bukan mengganti CV global profil. History/upload actor nullable untuk pengajuan publik. Lamaran baru Screening; duplikat pasangan email/lowongan tidak menambah record/file. Konflik unique email ditangani dengan retry aman; file dibersihkan saat transaksi gagal. Public response tidak mengirim id lamaran, profil, catatan, CV atau path.

Urutan: tests RED, DTO/controllers/migration/public workflow, landing+karier UI, update private DTO CV/history, full gate+review+browser. Tidak reset development, email, deploy atau push. Batas: belum verifikasi kepemilikan email, antivirus PDF, CAPTCHA, notifikasi atau portal pelamar.
