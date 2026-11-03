<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Rekap absensi {{ $report['month'] }}</title>
<style>
@page { margin:28px 30px 40px; }
body {font-family:'DejaVu Sans',sans-serif;font-size:8px;color:#182333;}
h1 {font-size:18px;margin:0 0 8px;} p {line-height:1.7;} .meta {color:#566272;}
table {border-collapse:collapse;width:100%;margin-top:14px;}
thead {display:table-header-group;} th {background:#f1f4f7;text-align:center;border-bottom:1px solid #bbc5d0;}
th,td {padding:7px 4px;word-wrap:break-word;overflow-wrap:break-word;vertical-align:top;}
td {text-align:right;border-bottom:1px solid #e1e6ed;} .person {text-align:left;} .department {text-align:left;} tr {page-break-inside:avoid;}
.note {font-size:7px;color:#566272;}
</style></head><body>
<p class="meta">HRIS Lite · Laporan HR</p><h1>Rekap absensi bulanan · {{ $report['month'] }}</h1>
<p class="meta">{{ $report['from'] }} s.d. {{ $report['to'] }} · {{ $report['total'] }} pegawai<br>{{ $report['status_label'] }}<br>Dibuat {{ \Carbon\CarbonImmutable::parse($report['generated_at'])->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB</p>
<p class="note">{{ $report['basis'] }}. Semua angka berupa hari-pegawai. Berjalan mencakup jadwal belum mulai/berlangsung dan belum pulang; mendatang tidak dihitung sebagai absen. Libur mengikuti kalender tanpa jadwal eksplisit. Cuti/izin hanya yang disetujui; konflik dengan punch masuk Perlu diperiksa. Roster historis dan kewajiban kerja tidak disimpulkan dari status pegawai saat ini.</p>
<table><thead><tr>@foreach($report['headers'] as $i=>$h)<th class="{{ $i===0 ? 'person' : ($i===1 ? 'department' : '') }}" style="width:{{ $i===0 ? '22%' : ($i===1 ? '12%' : '6%') }}">{{ $h }}</th>@endforeach</tr></thead><tbody>
@forelse($report['rows'] as $row)<tr>@foreach($row as $i=>$v)<td class="{{ $i===0 ? 'person' : ($i===1 ? 'department' : '') }}">{{ $v }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($report['headers']) }}">Tidak ada pegawai sesuai filter.</td></tr>@endforelse
</tbody></table></body></html>
