# Staging lokal melalui Cloudflare Tunnel

Target pengguna: kvndwi.my.id root menggantikan tujuan lama, tunnel dashboard-managed baru hris-lite, loopback127.0.0.1:8002. Route/domain lain tetap. Belum publish sampai instance siap dan pemeriksaan publikasi konkret selesai.

Isolasi: schema/user hris_lite_staging terpisah dari development/test, APP_KEY acak sendiri; .env.staging dan .local/staging-secrets.json ignored tidak dicetak. Storage upload/session/cache/log/view/temp staging sendiri, profil fail-closed menolak DB/env/storage/debug/cookie salah. Login UAT memakai akun acak, tidak password default. Data contoh dibuat eksplisit di DB staging; seeder local/testing yang ada tidak dibuka untuk staging. Mailer log tanpa pengiriman nyata. Build frontend existing, Vite hot file dev diabaikan.

Loopback proxy hanya dipercaya pada staging, header proto/IP dari cloudflared, hostname dibatasi kvndwi.my.id dan127.0.0.1. Cookie host-only secure/httpOnly/lax. PHP router staging tidak pernah mengeksekusi public/index.php dev atau menyajikan public/storage; static hanya ekstensi asset di bawah realpath public. DB3307 tidak diteruskan melalui tunnel. Tidak install runtime/dependency baru.

- [x] RED guard profil/proxy, GREEN4tes/13assertions; ingress/sesi diverifikasi HTTP lokal.
- [x] GREEN bootstrap/router/artisan staging, provisioning DB baru terbatas, data contoh dan akun acak tanpa stdout secrets; smoke local8002.
- [x] Review independen,185tes/2785assertions dan gates lulus; private files/login/exports diperiksa; worker staging aktif. Mismatch fixture akun cuti diperbaiki dan reseed staging berhasil.
- [x] Setelah konfirmasi eksplisit pengguna, tunnel hris-lite dibuat dan terhubung; route kvndwi.my.id→8002 aktif. Root CNAME diganti ke1641bd89-667d-476e-891f-d7474cb9ae06.cfargotunnel.com Proxied, recordlain tetap. HTTPS publik/guest restrictions dan browser loginHR/logout berhasil; lihat STAGING.md.

Nameserver/DNS utama sudah Cloudflare. DNS@ sekarang mengarah ke kevin-phone-tunnel lokal yang Down; service/kasir/www ikut tunnel lama, magang keVercel. Jangan menyentuh route lain atau merotasi token lama. Tunnel credentials tidak dicetak/di-commit. Hasil lokal bisa direview sebelum mengganti DNSroot.
