# Progress — docs/PLAN.md

## Phase 1 — 1 November 2026

- Pengguna menyetujui fondasi; bekerja langsung di checkout sesuai brief. Branch lokal feat/hris-foundation; tanpa remote/push.
- Composer 2.11.1 lokal dipasang setelah SHA384 installer diverifikasi. Network dan socket MySQL memerlukan eksekusi di luar sandbox.
- Skeleton Laravel v13.11.0 diunduh tanpa scripts/install ke staging .scaffold, manifest ditinjau lalu disalin tanpa menimpa docs.
- Ruling: PHPUnit 11.5 menggantikan default 12 agar kompatibel PHP 8.3.36. Tidak upgrade PHP major. Pail/Pao default tidak diperlukan.
- Ruling: AGENTS/CLAUDE bootstrap skeleton diganti catatan proyek. Boost tambahan tidak diperlukan scope dependency minimal pengguna; Composer tersedia lokal, bukan PATH global.
- MySQL 8.4.3 ditemukan di Laragon. Instance menggunakan .local/mysql-data pada 127.0.0.1:3307, tanpa menyentuh data Laragon lama. Root dan dua akun memakai password acak lokal; privilege hris_dev/hris_test hanya pada database masing-masing.
- Ruling: binary MySQL tersedia dipakai untuk demo loopback; bukan klaim patch terbaru. Update MySQL perlu sebelum production. Tidak memasang service/autostart atau mengaktifkan Docker.
- Development/test menerima tiga migration bawaan (user/session, cache, jobs); domain HR mulai Phase 2.
- RED: FoundationTest gagal "Not a valid Inertia response" sebelum route React. GREEN setelah middleware, Blade root dan welcome dibuat.
- Tes isolasi membuktikan hris_test tidak dapat SELECT hris_lite. Semua tes database memakai MySQL.
- Full suite awal gagal ExampleTest karena build belum ada; setelah Vite build seluruh 5 tes/18 assertions lolos.
- TypeScript, ESLint dan build lolos. Pint memperbaiki import pada dua file. Pemeriksaan akhir menyusul review.
- Belum ada login, akun demo, CRUD domain atau dashboard privat. Welcome menjelaskan tahap pengembangan tanpa angka palsu.

Berikutnya: Phase 2 — Fortify auth, role/policy, migration domain, factory dan seeder fiktif.

## Review dan perbaikan akhir

Review independen tidak menemukan defect penting pada scope Phase 1, dengan dua saran hardening. Keduanya ditangani karena berkaitan dengan setup dan isolasi database yang dijanjikan:
- DB_URL warisan terminal: regression menjalankan suite dengan URL kredensial invalid ke development awalnya menghasilkan dua kegagalan. Force ENV saja belum cukup pada PHP Windows karena SERVER juga dipakai. Setelah force pada keduanya, 5 tes/18 assertions lolos dengan URL invalid tersebut tetap terpasang. Database/user/driver test juga dipaksa pada SERVER.
- Skrip start MySQL sekarang menunggu listener localhost maksimum 30 detik, gagal eksplisit jika startup gagal, sehingga command berikut tidak berlomba dengan startup.
- npm audit awal menemukan 12 laporan moderate yang bersumber dari minimatch 10 transitif dev tooling. Override minimatch ^11.0.1 dipasang setelah memeriksa dukungan Node dan ekspor CommonJS/ESM; audit setelah install: 0 vulnerability. Evaluasi pencabutan override setelah dependency upstream memakai patch.
- Composer audit melaporkan tidak ada advisory, tetapi memperingatkan timeout repo.packagist.org dan fallback cache; ini bukan verifikasi advisory online penuh.
- Browser: halaman React berhasil dirender, tautan anchor berfungsi, viewport 375px tidak overflow horizontal, console warn/error kosong. Tidak mengklaim seluruh UI MVP sudah tersedia.

Phase 1 complete: final gate 5 tests / 18 assertions PASS; Pint PASS; ESLint PASS; Prettier PASS; TypeScript PASS; Vite build PASS; npm audit 0 vulnerabilities; Composer validate/platform requirements PASS; HTTP preview 200. Cold restart MySQL dan migrate:status PASS. Tidak ada commit, deploy, email atau push. File rahasia terverifikasi ignored. Keputusan dan keterbatasan dicatat di atas.

## Penyempurnaan desain halaman awal — 1 November 2026

- Iterasi pertama (landing product-first dengan mock dashboard dalam frame browser) dinilai pengguna masih generik. Iterasi kedua mengganti konsep ke arah berkas/editorial, bukan lagi landing SaaS.
- `resources/js/pages/welcome.tsx` ditulis ulang: masthead minimal, headline serif besar, indeks isi empat baris, tiga spesimen antarmuka (tabel pegawai, formulir, riwayat lamaran), status Phase 1 dengan cap, dan colophon dengan wordmark besar.
- `resources/js/components/specimens.tsx` baru berisi `PipelinePlate`, `EmployeeTable`, `EmployeeForm` dan `ApplicationTimeline`; `demo-dashboard.tsx` dihapus. Palet brief (#F8FAFC/#0F172A/#4F46E5/#10B981) dipertahankan, tanpa dependency atau font eksternal.
- Spesimen diberi label "Contoh tampilan. Data fiktif."; formulir tiruan `aria-hidden` agar tidak disalahartikan sebagai kontrol aktif. Status tidak hanya dibedakan warna.
- Verifikasi aktual: viewport 375px (iframe) tanpa overflow horizontal; Lighthouse accessibility/best-practices/SEO 100; console tanpa warning/error; npm lint, format:check, types:check dan build lolos; 5 tes/18 assertions lolos; Pint lolos.
- Login, akun demo dan fitur operasional tetap belum tersedia.

## Anti-AI-slop pass — 1 November 2026

- Pengguna menetapkan aturan anti-AI-slop. Indigo default `#4F46E5` dari brief diganti vermillion `#B42318` karena termasuk daftar aksen terlarang; token dan alasannya dicatat di `DESIGN.md`.
- Semua kelas `indigo-*` di halaman dan spesimen dihapus. Pipeline memakai skala slate + emerald; dot status "current" memakai aksen. Aksen kini muncul pada kata italic `h1`, titik wordmark, dan dot status.
- Ditambahkan token `:root` (`--paper`, `--ink`, `--accent`, `--ok`, `--muted`, `--line`), tema Tailwind, serta `data-od-id` pada masthead, hero, indeks, spesimen, status, colophon dan tiap figure.
- Metrik contoh dilabeli "angka contoh" pada header indeks dan "Contoh tampilan. Data fiktif." pada spesimen. Tidak ada gradient, emoji, kartu rounded, shadow, blob, gambar eksternal atau copy pengisi.
- Verifikasi: 375px tanpa overflow; Lighthouse accessibility/best-practices/SEO 100; console bersih; Pint, ESLint, Prettier, TypeScript dan build lolos; 5 tes/18 assertions lolos.

