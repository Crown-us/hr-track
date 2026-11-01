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
