# Taste pass — HRIS Lite

Inventory: berkas CV, pipeline screening/interview/offering, direktori pegawai, kontrak PKWT/PKWTT, pre/post-test, undangan wawancara, dokumen PDF, label Indonesia, akses akun dari pengelola, tiga komponen pilihan pengguna (bento/timeline/badge). Belum ada auth atau layanan operasional; semua contoh fiktif.

Argument: hero mengenalkan pengelolaan HR → bento memperlihatkan hubungan berkas → pemilih modul menjelaskan cakupan → cara kerja menunjukkan tindak lanjut → tabel/timeline/status menjadi bukti tampilan → akses akun menjelaskan jalur masuk sebenarnya.

Direction: editorial-report / The printed annual, seed 1718636150, palette/type kept. Pilihan ini membawa berkas dan riwayat kerja menjadi lembar laporan dengan sumbu kolom sekitar 42%, bukan deretan kartu fitur. CTA blueprint tiga foto diganti field informasi akses karena tidak ada foto produk/layanan yang nyata. Tidak menambah foto kantor generik atau testimoni.

Idea: pada halaman ini perjalanan berkas HRIS Lite menjadi susunan laporan yang dibaca dari informasi awal hingga tindak lanjut. Live thing: pemilih cakupan HR mengubah detail, daftar pekerjaan dan link preview; tombol keyboard native dengan aria-pressed/live region. FAQ akses juga native details.

Copy deck: HR yang lebih tertata. HRIS Lite menyatukan informasi pegawai, perjalanan kandidat dan administrasi kerja untuk tim HR. Jelajahi fitur. / Setiap berkas punya alur. / Satu ruang. Banyak urusan. / Jelas langkahnya. Terjaga konteksnya. / Kenali ruang kerjanya. / Mulai dengan akses yang tepat.

User overrides: bento asli, ikon status pastel dan timeline wajib dipertahankan; aturan skill tidak boleh menghapusnya. Data-ui hanya pada panel diagram dan fragmen aplikasi, bukan pembungkus seluruh halaman. Tidak ada gambar eksternal atau kredit foto.

ROUND 0 (before screenshots and report)
Seen in one second: komponen bento kecil di antara label dan garis.
Idea: berkas yang mengalir, tetapi hierarki halaman kurang kuat.
Live thing: diagram hover; tidak ada pilihan cakupan.
Layout: hero=small intro+bento / features=icon grid / workflow=numbered columns / preview=two panels / access=disabled button.
Scores: design 5.5 · usability 6 · creativity 6 · content 6.5 → overall 5.8
Caps: inspector 13 FAIL; tubuh 14px, judul 44px desktop/28px mobile.
Top problems: headline tidak memimpin fold; label kecil dan nomor dekoratif; fitur berupa kartu berulang. Fix: hero display, body 17–18px, pemilih fitur interaktif dengan detail substantif.

ROUND 1
Seen in one second: HR yang lebih tertata, judul memimpin dengan tindakan jelas.
Idea: perjalanan berkas menjadi laporan yang dapat dijelajahi.
Live thing: pemilih modul HR dengan detail yang berubah.
Layout: hero=asymmetric report / bento=retained animated interface / features=selector+sheet / workflow=ink field / preview=interface mosaic / access=question / footer=large wordmark.
Scores: design 6.5 · usability 7.5 · creativity 7 · content 7.5 → overall 6.0 (inspector cap)
Caps: 5 FAIL sebelum perbaikan berikutnya; komponen asli berisi diagram dan konvensi UI yang diminta pengguna.
Top problems and fixes:
1. Tablet workflow heading tiga baris (tablet-page-01): pendekkan menjadi Setiap langkah, jelas arahnya.
2. Pemilih fitur terpotong mobile (mobile-page-01): wrap tombol dalam dua baris, tidak horizontal scroll.
3. Kolom tabel dan timeline terlalu sempit di tablet: susun satu kolom di bawah 900px, tingkatkan kontras label tabel.
Inspector exceptions: icon-cards hanya untuk struktur tiga panel diagram bento yang diminta pengguna; tidak menghapus atau mengganti komponen tersebut demi skor. Tooltip avatar aria-hidden karena mengulang aria-label pada parent yang fokus keyboard; isi utama tetap terlihat dalam reduced motion.

ROUND 2 — final
Seen in one second: HR yang lebih tertata, judul serif besar dan tindakan Jelajahi fitur.
Idea: berkas HR sebagai lembar laporan yang dijelajahi dari cakupan hingga tindak lanjut.
Live thing: pemilih lima kebutuhan HR; diuji Pelatihan via klik dan Hubungan kerja via Tab/Enter. Informasi akses via details berhasil dibuka.
Layout words by section: hero=report / bento=animated plates / features=selector+sheet / workflow=ink field / preview=interface mosaic / access=question / footer=wordmark.
Scores: design 6.5 · usability 8 · creativity 7 · content 7.5 → overall 7.2 (self-review, bukan penilaian eksternal)
Caps applied: design 6.5 karena tanpa empat foto nyata; sengaja memakai antarmuka dan diagram produk yang diminta, bukan foto kantor generik. Belum mencapai 7.5 menurut rubric ketat skill ini.
Top three remaining limitations:
1. Diagram asli masih memiliki microcopy bahasa Inggris dan tipografi teknis kecil; dipertahankan sebagai komponen pilihan pengguna.
2. 19 ukuran font terukur mencakup ukuran fluid antar-viewport dan detail komponen asli; mengecilkan seluruh diagram agar menjadi lima ukuran akan merusak hierarki yang dipertahankan.
3. CTA akses belum login sungguhan karena auth di luar scope desain. Informasi akses menjelaskan status sebenarnya, tanpa tombol login palsu.

Final inspect: 0 FAIL, 4 WARN di 1440/768/390; --allow icon-cards untuk tiga diagram bento yang wajib dipertahankan. Warn outlined-box adalah shell bento asli; size-count karena detail UI dan skala fluid; no-real-pictures/no-footage karena seluruh media adalah produk itu sendiri. Tidak ada foto, klip eksternal, bundled photography atau data bisnis nyata yang dipakai. Seluruh contoh pegawai/kandidat fiktif dan harus diganti hanya setelah sistem memiliki data berizin.

Seluruh tujuh screenshot final dibuka: desktop-fold, desktop-page-01/02, tablet-fold/page-01, mobile-fold/page-01. Tidak ada overflow atau headline >2 baris. Pemeriksaan reduced motion tidak menemukan konten utama tersembunyi. Source Taste lint 0 FAIL/0 WARN. ESLint/TypeScript/Prettier/Vite PASS. FoundationTest 2 tests/22 assertions PASS. Seluruh anchor lokal memiliki target dan browser console warn/error kosong.

## Northstar — keputusan pengguna setelah Taste pass

Referensi pilihan pengguna menggantikan arah serif/editorial sebelumnya: sans-serif, hero center dan biru tua. Garis tipis serta shadow frame bento dipertahankan sesuai referensi. Pemeriksa Taste dipakai untuk menemukan overflow, wrap dan kegagalan teknis; larangan gaya tidak boleh mengalahkan pilihan pengguna. Tidak mengklaim skor/0 FAIL untuk pass ini. Lihat docs/PROGRESS.md untuk hasil aktual.
