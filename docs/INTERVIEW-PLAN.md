# Jadwal interview dan undangan

Scope: daftar/jadwalkan/batalkan interview internal, tautan dari detail lamaran, unduh PDF privat tanpa email. Admin/Staf HR mengelola; Viewer hanya melihat agenda dasar, tanpa kontak/tautan/catatan/undangan. Lamaran harus di tahap Interview. Input WIB disimpan UTC; durasi15–480menit; lokasi atau URL HTTPS; pewawancara Admin/Staf HR. Lock lamaran lalu user pewawancara untuk mencegah bentrok (pewawancara atau pelamar), interval bersinggungan di ujung diperbolehkan. Pembatalan mempertahankan riwayat/alasan/pelaku; keluar tahap Interview membatalkan jadwal belum selesai secara atomik. Jadwal pengganti dibuat setelah pembatalan; belum ada edit langsung/email/kalender eksternal.

Undangan Blade Dompdf memakai aset lokal saja, remote fetch/PHP/JS dimatikan; catatan internal tidak dimasukkan PDF. Dokumen diberi penanda demonstrasi sesuai brief. Tes RED→GREEN, seluruh quality gate, review terfokus dan browser. Tidak reset DB development atau push otomatis.
