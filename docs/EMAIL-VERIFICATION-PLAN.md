# Verifikasi email lamaran publik

Gunakan Laravel Notifications, antrean database khusus `recruitment` pada koneksi database aplikasi yang sama, serta temporary signed URL 24 jam. Tanpa dependency/provider baru. Mailer lokal log; inbox nyata membutuhkan konfigurasi SMTP dan worker, tidak menaruh kredensial dalam repository.

Tambahkan applications.email_verified_at dan verification_sent_at nullable, tanpa mengubah data lama. Lamaran publik yang belum diverifikasi tetap Screening dan HR tidak dapat mengubah tahap; input internal tetap mengikuti alur lama. Verifikasi hanya membuktikan akses mailbox untuk lamaran tersebut, bukan kebenaran nama/CV/identitas. Profil lama tidak ditimpa.

GET signed URL menampilkan konfirmasi tanpa mutasi agar pemindai email tidak memverifikasi otomatis; POST bertanda tangan + CSRF + throttle memverifikasi secara idempotent dan mengantrekan bukti penerimaan sekali. Hash email di URL mengikat ke email saat itu. Penutupan lowongan tidak membatalkan verifikasi lamaran yang sudah masuk. Tidak menyediakan akses CV/status melalui tautan tersebut.

Email verifikasi dan receipt merupakan queued notifications terenkripsi. Masukkan pekerjaan antrean dalam transaksi database yang sama (beforeCommit, koneksi recruitment memakai DB default), sehingga rollback mencakup data dan antrean; worker tidak dapat melihat row sebelum commit. Worker khusus `php artisan queue:work recruitment --queue=recruitment-mail --tries=3`. Penyedia email dapat mengirim ulang jika worker mati setelah mengirim tetapi sebelum ACK; tidak mengklaim exactly-once delivery.

Kirim ulang melalui form publik email saja, maksimal lima lamaran pending per permintaan; respons selalu generik, throttle IP dan cooldown10menit per lamaran. Duplikat pengiriman tidak mengganti CV/identitas. Nonexistent/archived/internal/verified tidak mengantrekan email baru. Antrean verifikasi lama dibatalkan secara logis saat email sudah diverifikasi/berubah atau record diarsip.

Verifikasi: tes notifikasi, signed/expiry/tamper, GET tanpa mutasi, CSRF, replay, mail cooldown, transaction rollback, blok workflow dan kedua alur keputusan sesudah verifikasi. UI mengikuti header/style yang sudah ada; cek browser desktop/mobile. Jalankan seluruh gate AGENTS. Tanpa push/deploy.
