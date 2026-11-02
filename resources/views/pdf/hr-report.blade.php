<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Laporan {{ $report['title'] }}</title>
<style>
@page { margin: 28px 30px 40px; }
body { font-family:'DejaVu Sans',sans-serif;font-size:9px;color:#182333; }
h1 {font-size:19px;margin:0 0 8px;font-weight:bold;} .brand{font-size:10px;color:#566272;margin:0 0 16px;}
.meta {color:#566272;line-height:1.8;margin:0 0 16px;} table{border-collapse:collapse;width:100%;table-layout:fixed;}
thead{display:table-header-group;} th{font-weight:bold;text-align:left;background:#f1f4f7;border-bottom:1px solid #b9c4d0;padding:9px 6px;}
td{border-bottom:1px solid #e1e6ed;padding:8px 6px;vertical-align:top;overflow-wrap:break-word;word-wrap:break-word;}
tr{page-break-inside:avoid;} .empty{color:#566272;padding:22px 6px;}.note{color:#566272;font-size:8px;margin-top:14px;}
</style></head><body><p class="brand">HRIS Lite · Laporan HR</p><h1>{{ $report['title'] }}</h1>
<p class="meta">{{ $report['basis'] }}: {{ $report['from'] }} s.d. {{ $report['to'] }} · {{ $report['status_label'] }}<br>
{{ $report['total'] }} baris · Dibuat {{ \Carbon\CarbonImmutable::parse($report['generated_at'])->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB</p>
<table><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value === '' ? '—' : $value }}</td>@endforeach</tr>
@empty<tr><td class="empty" colspan="{{ count($report['headers']) }}">Tidak ada data dalam periode dan status yang dipilih.</td></tr>@endforelse
</tbody></table><p class="note">Tanggal tanpa jam mengikuti kalender WIB. Kolom nilai kosong berarti belum dicatat.</p></body></html>
