<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Ringkasan penawaran</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#18253a;line-height:1.7;margin:30px}h1{font-size:24px;margin:8px 0 20px}h2{font-size:14px;margin-top:24px}table{width:100%;border-collapse:collapse}td{padding:8px;border-bottom:1px solid #ddd;vertical-align:top}td:first-child{width:34%;color:#56657a}.note{padding:14px;background:#f2f5fa;margin:20px 0}.terms{white-space:pre-wrap;overflow-wrap:break-word}</style></head>
<body>
<p>HRIS LITE · RINGKASAN INTERNAL #{{ $offer->id }}</p>
<h1>Ringkasan penawaran kerja</h1>
<p class="note">Periksa rincian penawaran bersama tim HR. Simpan dokumen penawaran dan bukti respons kandidat agar mudah ditelusuri.</p>
<table>
<tr><td>Kandidat</td><td>{{ $offer->application->applicant->full_name }}</td></tr>
<tr><td>Posisi lowongan</td><td>{{ $offer->application->vacancy->job_title }}</td></tr>
<tr><td>Penempatan</td><td>{{ $offer->department->name }} · {{ $offer->position->name }}</td></tr>
<tr><td>Status kerja</td><td>{{ \App\Enums\EmploymentStatus::from($offer->employment_status)->label() }}</td></tr>
<tr><td>Tanggal mulai</td><td>{{ $offer->join_date->format('d/m/Y') }}</td></tr>
<tr><td>Batas respons</td><td>{{ $offer->response_deadline->format('d/m/Y') }} (WIB)</td></tr>
<tr><td>Status penawaran</td><td>{{ ['pending'=>'Menunggu respons','accepted'=>'Diterima kandidat','declined'=>'Ditolak kandidat','withdrawn'=>'Ditarik HR'][$offer->status] }}</td></tr>
</table>
<h2>Ketentuan yang dicatat HR</h2><p class="terms">{{ $offer->terms ?: 'Belum ada ketentuan tambahan.' }}</p>
<p>Ringkasan diperbarui {{ $offer->updated_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB. Jika direvisi, gunakan ringkasan terbaru. Tidak memuat catatan seleksi atau dokumen CV.</p>
</body></html>
