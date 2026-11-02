# DESIGN — HRIS Lite

Dokumen ini adalah kontrak desain aktif. Aturan di bawah mengikat halaman dan komponen baru. Palet asli di `docs/BRIEF.md` memakai indigo `#4F46E5`; atas permintaan pengguna, indigo default diganti karena termasuk sinyal "AI slop". Warna pengganti adalah vermillion `#B42318`.

## Konsep

**Berkas.** Halaman awal dibaca seperti berkas personalia atau laporan, bukan landing page SaaS. Tipografi serif besar, garis rambut, indeks isi, spesimen antarmuka, cap status, dan colophon. Bahasa visual ini dipakai ulang untuk shell aplikasi.

## Token

| Token | Nilai | Pemakaian |
| --- | --- | --- |
| `--paper` | `#F8FAFC` | Latar utama |
| `--ink` | `#0F172A` | Teks dan garis kuat |
| `--accent` | `#B42318` | Maksimal 2 penggunaan terlihat per layar |
| `--ok` | `#10B981` | Status selesai/berhasil saja |
| `--muted` | `#64748B` | Teks sekunder |
| `--line` | `#CBD5E1` | Garis rambut |

- Display: Georgia / `var(--font-serif)`. Body: Segoe UI / `var(--font-sans)`. Label: mono uppercase, tracking lebar.
- Radius 0–2px. Tidak ada kartu rounded, shadow, blob, atau wave.
- Spacing mengikuti ritme 4/8/12/16/24/32/48/64/80.
- Setiap section wajib punya `data-od-id` agar comment mode bisa menargetkannya.

## Larangan P0

1. **Indigo default sebagai aksen.** Jangan pakai `#6366F1`, `#4F46E5`, `#4338CA`, `#3730A3`, `#8B5CF6`, `#7C3AED`, `#A855F7`. Gunakan `--accent`.
2. **Gradient "trust" dua stop** di hero. Permukaan datar + tipografi yang disengaja lebih baik.
3. **Emoji sebagai ikon fitur.** Gunakan SVG monoline 1.6–1.8 stroke dengan `currentColor`.
4. **Sans-serif untuk display** saat token serif aktif. `h1`/`h2` memakai `font-serif`.
5. **Kartu rounded dengan border kiri berwarna.** Buang radius atau border kirinya.
6. **Metrik karangan** seperti "10× faster" atau "99.9% uptime". Data demo wajib diberi label "angka contoh" / "data fiktif".
7. **Copy pengisi.** Tidak ada lorem ipsum, "fitur satu/dua/tiga", atau placeholder.

## Aturan P1

- Jangan memakai urutan Hero → Features → Pricing → FAQ → CTA tanpa variasi. Halaman ini memakai Hero → Indeks → Spesimen → Status → Colophon.
- Jangan memakai CDN gambar placeholder. Tidak ada gambar eksternal.
- Jangan menaruh lebih dari ~12 hex mentah di luar `:root`.
- `--accent` maksimal 2 penggunaan terlihat per layar. Saat ini: kata italic di `h1` dan titik wordmark; dot status "current" memakai aksen di layar lain.

## Aturan P2

- Tidak ada SVG blob/wave dekoratif.
- Layout tidak simetris sempurna: kolom hero 7/5, indeks 12 kolom, spesimen 6/3/3.
- Kontras kepadatan disengaja: hero lapang, indeks padat, status lapang.

## Jiwa

- **Satu gerakan berani:** headline serif besar dengan satu kata italic beraksen, di halaman yang dibaca seperti berkas.
- **Voice:** "Telusuri isi berkas", "Fondasi selesai. Isi menyusul.", "Setiap orang punya berkas. Setiap berkas punya alur."
- **Micro-interaction:** baris indeks bergaris bawah saat hover, panah muncul, dan CTA bergeser 1px saat ditekan.
- **Detail produk:** `#A-014`, "HR interview · 45 menit", "5 tes / 18 assertions", cap "FASE 01 · SELESAI".

## Karantina: halaman /lab

`/lab` menampilkan komponen impor `ink-orbit-features` sebagai spesimen, bukan bagian alur produk. Halaman ini **tidak** mengikuti aturan di atas dan tidak boleh dijadikan rujukan: komponen memakai gradient, shadow, sudut membulat, judul rata tengah, dan angka contoh di dalam diagram.

Aturan berlaku lagi begitu komponen dipindahkan ke halaman produk. Sebelum itu, minimal tiga hal harus dibereskan: token warna (ganti hex inline), tipografi display serif untuk `h2`, dan label data contoh. Selama masih di `/lab`, komponen tidak dihitung sebagai pelanggaran kontrak.

## Arahan terbaru pengguna — komponen referensi dipertahankan

Arahan ini mengungguli pembatasan lama yang bertentangan: bento animasi InkOrbit wajib tetap ada pada homepage, boleh diubah menjadi light. Homepage kini menggunakan tema light dengan permukaan putih/slate, tipografi display serif, serta semua diagram dan motion asli. Aturan karantina /lab di atas tidak lagi berlaku untuk bento homepage yang disetujui pengguna.

Referensi kedua diterapkan melalui ProcessTimeline: garis vertikal, lingkaran check untuk tahap selesai, lingkaran clock aktif, tahap mendatang abu-abu. Referensi ketiga melalui StatusBadge: kapsul pastel berikon, tujuh varian reusable. Radius pill dan warna semantik biru/violet diizinkan khusus status; pembatasan radius/aksen lama tidak boleh menghapus komponen yang diminta ini. Warna teks pastel digelapkan untuk keterbacaan pada light mode.

Data tetap fiktif. Timeline/badge diadaptasi dari screenshot pengguna; bukan klaim memakai source asli komponen yang tidak disertakan. Tidak ada perubahan backend.

## Landing produk — arahan pengguna terbaru

HRIS adalah aplikasi mandiri untuk kebutuhan magang. Landing menjelaskan fitur, cara kerja, dan cuplikan produk; hapus narasi portofolio, progres fase, stack dan jumlah tes dari UI. Struktur aktif: navigasi → bento animasi light → cakupan fitur → cara kerja → tabel/timeline/badge → akses akun → footer. Bento, ProcessTimeline dan StatusBadge dipertahankan. Akses akun diberi status belum tersedia selama autentikasi belum diimplementasikan. Cakupan training/kontrak/laporan dipresentasikan sebagai layanan yang sedang disiapkan.

## Taste Skill pass — arah aktif

Gunakan The printed annual (editorial-report, seed 1718636150) untuk landing. Judul HR yang lebih tertata memimpin fold; white ground, navy ink, field sage tenang dan CTA biru tua. Body 17–18px. Sumbu kolom sekitar 42% di desktop; komposisi mobile ditata ulang. Fitur menjadi pemilih cakupan interaktif, cara kerja satu field ink, contoh aplikasi mosaic dan footer wordmark besar. Bento, timeline dan badge pastel dipertahankan sebagai pengecualian eksplisit aturan skill; tanpa penghapusan untuk mengejar skor. Detail keputusan/critique di docs/TASTE-REVIEW.md. Aturan lama terkait hairline/label mikro hanya berlaku dalam fragmen UI yang dipertahankan, bukan landing secara keseluruhan.

## Arah aktif: referensi Northstar yang disetujui pengguna

Mengungguli arah printed-annual sebelumnya. Landing memakai sans-serif sistem, hero rata tengah, headline dua baris dengan aksen biru tua #233F8D, dua CTA anchor yang berfungsi, navigasi ringan, dan menu fitur di kiri dengan contoh isi di kanan. Garis 1px, radius ringan, serta soft shadow pada frame bento diizinkan secara eksplisit untuk mengikuti referensi pengguna. Tidak menjalankan ulang draw untuk mengganti referensi pilihan pengguna. Kode dan aset Northstar tidak diunduh/disalin; implementasi lokal dari pengamatan komposisi. Bento, timeline dan badge tetap dipertahankan.

## Tipografi aktif — 2 November 2026

Pilihan pengguna: Space Grotesk untuk headline, heading section, wordmark, judul bento; DM Sans untuk body/menu/tabel/badge dan teks diagram. Headline weight 600 dengan tracking -0.04em. Font static 400/500/600/700 di public/fonts, di-host lokal dengan font-display:swap. Lisensi OFL kedua keluarga disertakan. Tidak memakai Plus Jakarta Sans.

## Tipografi aktif — pendekatan Apple, 2 November 2026

Satu keluarga netral menggantikan pairing sebelumnya. Native -apple-system/BlinkMacSystemFont pada Apple; Inter lokal pada platform lain, fallback Segoe UI. SF Pro tidak diunduh atau didistribusikan karena lisensi unduhannya membatasi konten website umum (developer.apple.com/fonts, License 2B). Headline semibold600, tracking -0.045em desktop/-0.04em mobile; body regular400. Body hero20px desktop/17px mobile, spacing hero100px desktop/54px mobile. Font Inter beserta OFL disimpan public/fonts, font-display swap. Komponen bento/timeline/badge tetap.

## Ruang kerja HR — pratinjau desain

Sidebar putih/abu netral 224px desktop, aktif abu ringan, ikon outline satu set. Ringkasan angka tanpa ikon dekoratif; agenda dan antrian tinjauan lebih utama dari grafik. Board lima tahap menggunakan satu frame horizontal, tanpa scrollbar vertikal per kolom. Font native Apple/Inter; badge pastel dan ProcessTimeline dipakai ulang. Mobile: navigasi drawer tersembunyi dari urutan keyboard saat ditutup; tabel/board scroll dalam frame. Route preview hanya local/testing; data fiktif bertanggal contoh2 November2026. Tidak dianggap fitur HR operasional sampai login/policy/database terpasang.
