# HRIS Lite — ERD dan rancangan migration

Pembaruan akun: migration000012 menambah users.is_active defaulttrue, employee_id nullable unique FK RESTRICT, version/auth_version default1; employees.manager_employee_id nullable FK RESTRICT ke employees; account_invitations.employee_id nullable FK/version. account_administration_lock singleton id1 menyerialisasi mutasi akun/undangan/aktivasi/reset/struktur atasan; account_events append-only (actor/target_user/employee/invitation FK nullable RESTRICT, action, changes JSON, created_at UTC; tanpa password/token). Tidak ada CHECK self-manager karena MySQL menolak rujukan kolom auto_increment; self/cycle dicegah traversal service di bawah mutex/rowlock. Undangan aktif mereservasi pegawai lewat pemeriksaan transaksi, users.employee_id unique tetap menjaga satu akun per pegawai. Reset memakai password_reset_tokens bawaan (hash/expiry/single use) di transaksi yang sama. Role dan batas portal ditegakkan backend.

Pembaruan pelanggaran/SP: migration000011 menambah disciplinary_cases (employee_id FK RESTRICT, reference_number unique100, kind violation/sp1/sp2/sp3/counseling, incident_on date WIB, subject255, description privat, follow_up_on date nullable>=kejadian, status open/resolved/voided, version, created_by FK, timestamps UTC), discipline_documents (disciplinary_case_id+version unique, uploaded_by FK, UUID path unique, size1–5MiB, created_at UTC), discipline_events append-only (disciplinary_case_id, actor_id FK, action, changes JSON before/after/reason, created_at UTC). CHECK jenis/status/periode/file. Pegawai/jenis immutable; lock pegawai lalu catatan menyerialisasi koreksi/status/alokasi versi. Tidak ada tabel laporan baru: query terbatas dari applications, training_participants+trainings, employee_contracts dan disciplinary_cases dengan kolom terpilih. Ekspor sementara, tidak menyalin uraian/telepon/email/path/audit. Viewer tidak mengakses modul SP dan laporan.

Pembaruan kontrak: migration000010 menambah employee_contracts (employee_id FK RESTRICT, contract_number unique100, type pkwt/pkwtt, starts_on/ends_on date, notes privat, voided_at UTC, created_by FK, version, timestamps UTC), contract_documents (employee_contract_id+version unique, uploaded_by FK, UUID path unique, size_bytes1–5MiB, created_at), contract_events append-only (employee_contract_id, actor_id FK, action, changes JSON snapshot, created_at UTC). CHECK PKWT wajib akhir>=mulai; PKWTT akhir null. Semua FK RESTRICT. Lock pegawai lalu kontrak menyerialisasi pengecekan overlap periode inklusif serta alokasi versi dokumen; pegawai immutable. Koreksi/pembatalan wajib alasan dan versi. Disk contract_documents privat; data Viewer tidak berisi notes/path/berkas/audit. Tanggal kalender WIB; pengingat mengecualikan pegawai arsip/nonaktif dan kontrak dengan perpanjangan tanpa jeda. Tidak otomatis mengubah status pegawai.

Pembaruan Pelatihan: migration000009 menambah trainings (judul, pemateri, lokasi, waktu UTC, scheduled/completed/cancelled, notes privat, version, created_by FK), training_participants (training_id+employee_id unique, attendance registered/attended/absent, pre_test/post_test decimal5,2 nullable0–100, notes, version), training_materials (training_id+version unique, uploaded_by, UUID path unique, size_bytes≤5MiB, created_at), training_events (training_id, actor_id, action, changes JSON, created_at; append-only). FK RESTRICT; CHECK waktu, status, skor dan pasangan post-test. Materi disk privat; metadata tidak mengirim path. Pegawai relasi peserta memakai withTrashed untuk riwayat. Waktu sesi tidak dapat dipindah setelah nilai pernah muncul pada audit, meskipun nilai kini kosong. Rata-rata hanya pasangan lengkap peserta hadir, selisih post−pre dalam poin.

Pembaruan Offering: migration000008 menyediakan recruitment_offers (application_id unique, department/position FK gabungan, employment_status, join_date, response_deadline, terms nullable, status pending/accepted/declined/withdrawn, response_date/response_note/evidence_path/evidence_size nullable, timestamps UTC). recruitment_offer_events append-only: recruitment_offer_id, actor_id FK RESTRICT, action, changes JSON snapshot, created_at UTC. Employees menambah applicant_id dan source_application_id nullable+unique+FK RESTRICT, mencegah duplikasi konversi termasuk arsip. Tanggal berupa date WIB, file respons privat. Application.version mengunci revisi/respons; konversi memakai lock aplikasi/profil dan unique DB. Rollback migration ditolak bila ada penawaran/hasil konversi untuk menjaga audit.

Pembaruan verifikasi lamaran publik: migration000007 menambah applications.email_verified_at dan verification_sent_at nullable (UTC). Verifikasi per lamaran, tanpa perubahan profil pelamar atau user internal; HR tidak dapat mengubah tahap lamaran publik sebelum verified. Pekerjaan email terenkripsi memakai tabel jobs Laravel pada koneksi database aplikasi yang sama agar ikut transaksi. Lihat EMAIL-VERIFICATION-PLAN.md.

Status: usulan Phase 1, belum migration executable. Baca [PLAN.md](PLAN.md) untuk business rules dan scope.

Pembaruan ATS: migration000004 menyediakan applicants, applications (version integer), application_status_histories (note nullable) dan applicant_documents. Path CV privat disimpan di DB; disk/MIME/nama unduh ditetapkan server secara tetap, bukan kolom disk/original_name/mime_type pada rancangan awal. Unique email dan pasangan profil/lowongan, FK RESTRICT, timestamps UTC. Jadwal interview dan arsip domain ini belum tersedia.

Pembaruan lowongan: migration000003 menyediakan vacancies dengan version integer serta CHECK tanggal/status/jenis kerja. Lowongan internal sudah dapat tambah/baca/edit/status; belum ada hapus/arsip lowongan di UI, halaman karier atau tabel kandidat/aplikasi. Daftar aktif memakai tanggal WIB, penyimpanan timestamp tetap UTC.

Pembaruan pegawai 2 November: departments, positions dan employees sudah executable pada migration000002. Employees menambah version unsigned integer untuk konflik formulir. Employee_events menyimpan employee_id FK, actor_id FK, action, reason nullable, changes JSON nullable, document_path unik nullable, document_size nullable dan created_at UTC. FK hard delete RESTRICT; file disk privat. Arsip/restore melalui UI Admin sekarang tersedia sesuai permintaan terbaru; rancangan awal yang menunda restore dikesampingkan untuk pegawai. Domain rekrutmen di bawah masih rancangan.

Pembaruan autentikasi: migration `2026_11_02_000001` menambah `users.role` (default viewer) dan tabel `account_invitations`: id, email unik, role, token_hash unik, expires_at, used_at nullable, timestamps UTC. Tabel domain di bawah masih rancangan. Token undangan mentah hanya disampaikan ke penerima; database menyimpan hash SHA-256, bukan token.

```mermaid
erDiagram
    departments ||--o{ positions : memiliki
    departments ||--o{ employees : menempatkan
    positions ||--o{ employees : menjabat
    departments ||--o{ vacancies : membuka
    applicants ||--o{ applications : melamar
    vacancies ||--o{ applications : menerima
    applications ||--|{ application_status_histories : mencatat
    users ||--o{ application_status_histories : mengubah
    applications ||--o{ interviews : menjadwalkan
    users ||--o{ interviews : mewawancarai
    applicants ||--o{ applicant_documents : memiliki
    users ||--o{ applicant_documents : mengunggah

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        varchar role
        boolean is_active
        timestamp email_verified_at "nullable"
        varchar remember_token "nullable"
        timestamp created_at
        timestamp updated_at
    }
    departments {
        bigint id PK
        varchar code UK
        varchar name UK
        timestamp created_at
        timestamp updated_at
    }
    positions {
        bigint id PK
        bigint department_id FK
        varchar name "unique within department"
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        varchar employee_number UK
        varchar full_name
        varchar work_email UK
        varchar phone "nullable"
        bigint department_id FK
        bigint position_id FK
        varchar employment_status
        date join_date
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "nullable"
    }
    vacancies {
        bigint id PK
        bigint department_id FK
        varchar job_title
        varchar employment_type
        varchar location
        text description
        text requirements
        date opening_date
        date closing_date
        varchar status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "nullable"
    }
    applicants {
        bigint id PK
        varchar full_name
        varchar email UK
        varchar phone "nullable"
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "nullable"
    }
    applications {
        bigint id PK
        bigint applicant_id FK
        bigint vacancy_id FK
        date application_date
        varchar source "internal or public"
        timestamp email_verified_at "nullable, per application"
        timestamp verification_sent_at "nullable, resend cooldown"
        varchar submitted_name "nullable, unverified public submission"
        varchar submitted_phone "nullable, unverified public submission"
        text notes "nullable"
        varchar status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "nullable"
    }
    application_status_histories {
        bigint id PK
        bigint application_id FK
        bigint actor_id FK "nullable for public submission"
        varchar old_status "nullable only on creation"
        varchar new_status
        timestamp created_at
    }
    interviews {
        bigint id PK
        bigint application_id FK
        bigint interviewer_id FK
        timestamp starts_at
        timestamp ends_at
        varchar location "nullable"
        varchar meeting_url "nullable"
        text notes "nullable"
        timestamp cancelled_at "nullable"
        timestamp created_at
        timestamp updated_at
    }
    applicant_documents {
        bigint id PK
        bigint applicant_id FK
        bigint uploaded_by FK "nullable for public submission"
        bigint application_id FK "nullable for internal shared CV"
        varchar disk
        varchar path UK
        varchar original_name
        varchar mime_type
        bigint size_bytes
        timestamp created_at
        timestamp updated_at
    }
```

## Konvensi kolom dan integritas

- Semua id unsigned bigint auto-increment; FK unsigned bigint, InnoDB, utf8mb4. Semua kolom wajib kecuali ditandai nullable. Timestamps UTC; date tidak dikonversi zona waktu.
- String default 255, phone 32, role/status 32, employee_number/code 50, meeting_url 2048. Email disimpan lowercase dan unique; validasi tetap mempertahankan constraint database untuk race conditions.
- `users.role`: admin/hr_staff/viewer, default viewer; `is_active` default true. Akun dinonaktifkan, bukan hard delete, agar actor audit tetap valid. Session middleware menolak akun nonaktif.
- `employees.employment_status`: permanent/contract/probation/intern/inactive. `vacancies.employment_type`: full_time/part_time/contract/internship. Keduanya domain terpisah dan label UI Indonesia.
- `vacancies.status`: draft/published/closed, default draft. `applications.status`: screening/interview/offering/accepted/rejected, default screening. Gunakan PHP backed enums dan CHECK database untuk daftar nilai serta rentang tanggal di MySQL yang mendukung enforced CHECK.
- `positions`: unique (department_id, name) dan unique (id, department_id). `employees` mempunyai FK department_id serta FK gabungan (position_id, department_id) ke positions(id, department_id), sehingga jabatan departemen lain ditolak database juga.
- `applications`: unique (applicant_id, vacancy_id), termasuk ketika diarsip. Tidak ada reapply/reopen pada MVP. `application_date` dan notes milik aplikasi, bukan identitas kandidat.
- `application_status_histories`: append-only; tanpa updated_at atau soft delete. Creation history old_status null; pengajuan publik actor_id null, setiap transisi HR mencatat actor session dan waktu. Kardinalitas minimal satu history dijamin service transaction, bukan FK saja.
- `applicant_documents`: satu row per versi CV, file UUID unik, disk privat yang ditetapkan server. CV publik mempunyai application_id dan uploaded_by null; akses dibatasi ke lamaran itu. CV internal dengan application_id null dapat dibagikan lintas lamaran profil. UI menampilkan versi terbaru dan daftar versi hanya kepada admin/HR. Path/original_name tidak pernah menjadi path input unduh.
- Pengajuan publik menyimpan nama/telepon belum terverifikasi pada applications, tanpa menimpa profil yang sudah ada. Migration000006 menolak rollback jika masih ada data publik/actor null agar audit tidak hilang.
- FK menggunakan RESTRICT pada hard delete; master departemen/jabatan tidak dihapus jika direferensikan. Tidak ada cascade hard delete audit atau file. Penghapusan employee/vacancy/applicant berarti soft delete; kandidat diarsip beserta aplikasi dan pembatalan interview mendatang secara transaksional. Lowongan arsip menolak aplikasi baru namun history tetap terbaca.
- Wawancara dihapus secara logis lewat cancelled_at; jangan hilangkan rekam jejak jadwal. Listing aktif mengecualikan cancelled_at dan induk arsip. Blok bentrok jadwal dengan transaction + lock user interviewer, lalu cek overlap starts_at < proposed_end dan ends_at > proposed_start.
- Unique email/nomor pegawai tidak dilepas saat soft delete; validasi menjelaskan bahwa nilai telah dipakai. Restore dan hard delete bukan scope UI MVP.

## Index dan constraint tambahan

| Tabel | Index/check |
| --- | --- |
| employees | (department_id, employment_status), full_name, join_date; FK gabungan position/department |
| vacancies | (status, opening_date, closing_date), department_id; closing_date >= opening_date |
| applicants | full_name, unique email |
| applications | unique (applicant_id, vacancy_id), (vacancy_id, status), (status, application_date) |
| application_status_histories | (application_id, created_at, id); actor FK; index created_at untuk aktivitas terbaru |
| interviews | (interviewer_id, starts_at, ends_at), (application_id, starts_at), starts_at; ends_at > starts_at |
| applicant_documents | (applicant_id, created_at), unique path; size_bytes > 0 dan <= 5242880 |

Pencarian substring `%term%` terikat parameter, sesuai ukuran demo; index nama tidak diklaim mempercepat semua substring. Jika skala bertambah, ukur query sebelum menambah full-text. Sorting SQL hanya dari whitelist, bukan nama kolom bebas dari request.

## Urutan migration yang direncanakan

1. users (adaptasi migration Laravel), session dan tabel pendukung framework yang benar-benar dipakai.
2. departments.
3. positions dengan composite unique keys.
4. employees beserta composite FK.
5. vacancies.
6. applicants.
7. applications.
8. application_status_histories.
9. interviews.
10. applicant_documents.

Down migration berjalan dalam urutan terbalik. Setiap migration mempunyai nama timestamp Laravel sesuai urutan ini. Jangan menjalankan migrate:fresh pada database user yang sudah berisi data.

## Seed yang diusulkan

3 akun role demo, 5 departemen, 10 jabatan, 30 pegawai, 6 lowongan berbagai status, 24 kandidat, 30 aplikasi dengan seluruh tahapan pipeline, dan 8 interview (termasuk riwayat/pembatalan). Seeder membuat history yang konsisten melalui workflow domain; interview mendatang hanya untuk tahap interview. CV fixture berupa PDF sintetis tanpa data nyata. Password berasal dari env development; README mendokumentasikan email demo dan cara menentukan password lokal.

Tidak ada tabel kontrak pada MVP. Dashboard tidak menampilkan preview kontrak sampai schema dan scope kontrak disetujui terpisah.

Pembaruan interview: migration000005 menambah interviews sesuai rancangan ditambah created_by, cancelled_by, cancel_reason dan version integer. Waktu UTC ditampilkan WIB. Pembatalan mempertahankan row; belum ada hard delete atau edit langsung.
