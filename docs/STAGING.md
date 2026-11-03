# Staging lokal — kvndwi.my.id

Instance staging disiapkan pada `127.0.0.1:8002`. MySQL harus sudah hidup pada port3307. Database/user, APP_KEY, sesi, cache, antrean, upload dan log terpisah dari development/test. Tidak menyalin data development. Profil dan akun acak disimpan privat di `.env.staging` dan `.local/staging-secrets.json`; keduanya ignored, jangan commit atau bagikan file tersebut.

Menyalakan aplikasi dari PowerShell di root checkout:

```powershell
php -d display_errors=0 -S 127.0.0.1:8002 -t public scripts/staging-router.php
```

Terminal lain untuk antrean staging:

```powershell
php scripts/staging-artisan.php queue:work --sleep=3 --tries=1 --timeout=60
```

Gunakan `scripts/staging-artisan.php` untuk perintah staging; `php artisan` biasa tetap development. Tidak perlu migrate ulang saat restart. Jangan menjalankan migrate:fresh. Build frontend melalui `npm run build`; staging mengabaikan public/hot development. Setelah perubahan konfigurasi/profil, restart server dan worker.

Tunnel aktif: `hris-lite` (`1641bd89-667d-476e-891f-d7474cb9ae06`), published application root `kvndwi.my.id`, service `http://127.0.0.1:8002`. Setelah konfirmasi pengguna, DNS CNAME root diganti ke tunnel baru (Proxied); subdomain lain dan kvndwi.com tidak diubah. Token connector disimpan privat di `.local/staging/cloudflared-token.txt`; jangan dicetak, commit atau dimasukkan screenshot.

Terminal ketiga untuk menghidupkan connector kembali:

```powershell
& 'C:\Program Files (x86)\cloudflared\cloudflared.exe' tunnel --no-autoupdate --logfile '.local/staging/cloudflared.log' run --token-file '.local/staging/cloudflared-token.txt'
```

Cookie secure membutuhkan akses HTTPS melalui domain untuk login browser. Pemeriksaan lokal memakai host/proto connector tanpa menurunkan keamanan cookie. PC, MySQL, server PHP, queue worker dan cloudflared perlu tetap hidup. Ini instance staging dari komputer lokal; belum layanan hosting selalu aktif. Pengiriman email memakai mailer log, sehingga verifikasi/reset melalui email belum terkirim ke penerima.

Verifikasi 3 November2026:185tes/2785assertions lulus; Pint, lint, format, types dan build lulus. HTTP lokal landing/login/karier200, private guest302; .env/.env.staging404, storage403; aset tanpa Vite hot. Login akun staging berhasil, HR/rekap dan ekspor XLSX/PDF200, logout302. Review independen menemukan mismatch identitas fixture cuti yang sudah diperbaiki dan seeding staging berhasil. Tidak ada commit/push.

HTTPS publik diverifikasi: landing/login/karier200, HR tamu302 ke HTTPSlogin, env404/storage403. Browser berhasil login akun staging ke dashboard HR dengan data staging, console tanpa error/warning, lalu logout ke beranda. Probe Guzzle publik tidak dijalankan ulang karena CA PHP lokal belum mengenali issuer; verifikasi TLS tetap aktif dan pemeriksaan publik memakai browser serta trust store Windows. Ekspor diuji melalui instance lokal staging, bukan diulang lewat browser publik.
