{{--
    PDF template rendered by dompdf. Inline styles are intentional here: dompdf
    has no Tailwind pipeline, so the "no custom CSS" UI rule does not apply to
    this print artefact.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Siswa — {{ $dataset->studentName }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .03em; color: #444; }
        .meta { color: #555; font-size: 10px; margin-bottom: 4px; }
        .meta span { margin-right: 14px; }
        .identity { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px 14px; margin-top: 12px; }
        .identity div { margin-bottom: 3px; }
        .summary { display: table; width: 100%; }
        .summary .cell { display: table-cell; width: 25%; border: 1px solid #d1d5db; padding: 8px; text-align: center; }
        .summary .cell strong { display: block; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { text-align: center; color: #777; padding: 12px; }
        .footer { margin-top: 18px; color: #777; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Rekap Siswa</h1>
    <div class="meta">
        <span><strong>Cakupan:</strong> {{ $dataset->scope }}</span>
        <span><strong>Dibuat oleh:</strong> {{ $dataset->requestedBy }}</span>
        <span><strong>Waktu:</strong> {{ $dataset->generatedAt }}</span>
    </div>

    <div class="identity">
        <div><strong>Nama:</strong> {{ $dataset->studentName }} ({{ $dataset->nis }})</div>
        <div><strong>Kelas:</strong> {{ $dataset->className }}</div>
        <div><strong>Perusahaan:</strong> {{ $dataset->companyName }}</div>
        <div><strong>Guru Pembimbing:</strong> {{ $dataset->teacherName }}</div>
        <div><strong>Periode PKL:</strong> {{ $dataset->period }}</div>
    </div>

    <h2>Absensi</h2>
    <div class="summary">
        <div class="cell"><strong>{{ $dataset->attendancePresent }}</strong>Hadir</div>
        <div class="cell"><strong>{{ $dataset->attendancePermit }}</strong>Izin</div>
        <div class="cell"><strong>{{ $dataset->attendanceSick }}</strong>Sakit</div>
        <div class="cell"><strong>{{ $dataset->attendanceAbsent }}</strong>Alpa</div>
    </div>
    <p class="meta">Tingkat kehadiran: <strong>{{ $dataset->attendanceRate }}</strong></p>

    <h2>Jurnal</h2>
    <div class="summary">
        <div class="cell"><strong>{{ $dataset->journalTotal }}</strong>Total</div>
        <div class="cell"><strong>{{ $dataset->journalApproved }}</strong>Disetujui</div>
        <div class="cell"><strong>{{ $dataset->journalPending }}</strong>Pending</div>
        <div class="cell"><strong>{{ $dataset->journalRejected }}</strong>Ditolak</div>
    </div>

    <h2>Nilai Akhir</h2>
    @if ($dataset->finalScore === null)
        <p class="empty">Belum dinilai.</p>
    @else
        <div class="summary">
            <div class="cell"><strong>{{ $dataset->industryScore }}</strong>Nilai Industri</div>
            <div class="cell"><strong>{{ $dataset->teacherScore }}</strong>Nilai Guru</div>
            <div class="cell"><strong>{{ $dataset->finalScore }}</strong>Nilai Akhir</div>
            <div class="cell"><strong>{{ $dataset->grade }}</strong>Grade ({{ $dataset->assessmentStatus }})</div>
        </div>
    @endif

    <h2>Kunjungan Monitoring</h2>
    @if (count($dataset->monitorings) === 0)
        <p class="empty">Belum ada kunjungan.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Catatan</th>
                    <th>Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dataset->monitorings as $visit)
                    <tr>
                        <td>{{ $visit['visit_date'] }}</td>
                        <td>{{ $visit['note'] }}</td>
                        <td>{{ $visit['recommendation'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
